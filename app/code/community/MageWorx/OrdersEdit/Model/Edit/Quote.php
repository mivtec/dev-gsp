<?php
/**
 * MageWorx
 * Admin Order Editor extension
 *
 * @category   MageWorx
 * @package    MageWorx_OrdersEdit
 * @copyright  Copyright (c) 2016 MageWorx (http://www.mageworx.com/)
 */

class MageWorx_OrdersEdit_Model_Edit_Quote extends Mage_Core_Model_Abstract
{
    protected $_orderItems = array();

    /**
     * Apply all the changes to quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array $data
     * @return Mage_Sales_Model_Quote
     */
    public function applyDataToQuote(Mage_Sales_Model_Quote $quote, array $data)
    {
        foreach ($data as $key => $value) {
            if ($key == 'shipping_address') {
                $this->setAddress($quote, $value, 'shipping');
            } elseif ($key == 'billing_address') {
                $this->setAddress($quote, $value, 'billing');
            } elseif ($key == 'payment') {
                $this->setPayment($quote, $value);
            } elseif ($key == 'shipping') {
                $this->setShipping($quote, $value);
            } elseif ($key == 'quote_items') {
                $this->updateItems($quote, $value);
            } elseif ($key == 'product_to_add' && !empty($value)) {
                $this->addNewItems($quote, $value);
            } elseif ($key == 'coupon_code') {
                $this->setCouponCode($quote, $value);
            }
        }

        // Clear quote from canceled items
        $this->clearQuote($quote);

        // If multifees enabled
        $this->collectMultifees();

        $quote->setTotalsCollectedFlag(false)->collectTotals();
        Mage::dispatchEvent('mwoe_apply_data_to_quote_collect_totals_after', array(
            'quote' => $quote,
            'new_data' => $data
        ));

        $this->saveTemporaryItems($quote, 1, true);

        if (isset($data['coupon_code'])) {
            $valid = $this->validateCouponCode($quote, $data['coupon_code']);
            $valid ? $quote->getShippingAddress()->setCouponCode($data['coupon_code']) : null;
        }

        return $quote;
    }

    /**
     * Apply shipping/billing address to quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $data
     * @param $addressType
     * @return $this
     */
    public function setAddress(Mage_Sales_Model_Quote $quote, $data, $addressType)
    {
        $address = ($addressType == 'shipping') ? $quote->getShippingAddress() : $quote->getBillingAddress();
        $address->addData($data);

        // fix for street fields
        $streetArray = array();
        for ($i = 0; $i < 4; $i++) {
            if (isset($data['street[' . $i]) && $data['street[' . $i]) {
                $streetArray[$i] = $data['street[' . $i];
            }
        }
        $street = implode(chr(10), $streetArray);
        $streetData = array('street' => $street);
        $address->addData($streetData);
        // fix end

        return $this;
    }

    /**
     * Apply payment method to quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $data
     * @return $this
     */
    public function setPayment(Mage_Sales_Model_Quote $quote, $data)
    {
        $payment = $quote->getPayment();
        if (isset($data['cc_number']) && !isset($data['cc_number_enc'])) {
            $data['cc_number_enc'] = $payment->encrypt($data['cc_number']);
            unset($data['cc_number']);
            $payment->setCcNumberEnc($data['cc_number_enc']);
        }
        $payment->addData($data);

        return $this;
    }

    /**
     * Apply shipping method data to quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $data
     * @return $this
     */
    public function setShipping(Mage_Sales_Model_Quote $quote, $data)
    {
        $address = $quote->getShippingAddress();

        if (isset($data['custom_price'])) {
            Mage::getSingleton('adminhtml/session')->setShippingEdited(true);
            $shippingCustomPrice = $data['custom_price'];
            $order = Mage::registry('ordersedit_order');
            if ($order) {
                $rate = $order->getBaseToOrderRate();
                $baseShippingCustomPrice = round($shippingCustomPrice / floatval($rate), 4);
            } else {
                $baseShippingCustomPrice = $shippingCustomPrice;
            }
            Mage::getSingleton('adminhtml/session_quote')->setBaseShippingCustomPrice($baseShippingCustomPrice);
            Mage::getSingleton('adminhtml/session_quote')->setShippingCustomPrice($shippingCustomPrice);
        } else {
            Mage::getSingleton('adminhtml/session_quote')->setBaseShippingCustomPrice(null);
            Mage::getSingleton('adminhtml/session_quote')->setShippingCustomPrice(null);
        }

        if (isset($data['shipping_method'])) {
            Mage::getSingleton('adminhtml/session')->setShippingEdited(true);
            $address->setShippingMethod($data['shipping_method']);
        }

        return $this;
    }

    /**
     * Apply updated order items to quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $data
     * @return $this
     */
    public function updateItems(Mage_Sales_Model_Quote $quote, $data)
    {
        foreach ($data as $itemId => $params) {
            $quoteItem = $quote->getItemById($itemId);
            if (!$quoteItem) {
                continue;
            }

            // Set empty action by default to prevent warnings, if no one action was specified
            if (!isset($params['action'])) {
                $params['action'] = '';
            }

            // If item not exist in quote, create a new one
            if (!$quoteItem && $params['action'] != 'remove') {
                $this->addNewItems($quote, array($itemId => $params));
                $quoteItem = $quote->getItemById($itemId);
                if (!$quoteItem) {
                    continue;
                }
            }

            if ((isset($params['action']) && $params['action'] == 'remove')
                || ((isset($params['qty']) && $params['qty'] < 0.001))
            ) {
                $childrens = $quoteItem->getChildren();
                foreach ($childrens as $childQuoteItem) {
                    $quote->getItemsCollection()->removeItemByKey($childQuoteItem->getId());
                }
                $quote->getItemsCollection()->removeItemByKey($quoteItem->getId());
                continue;
            }

            if (isset($params['qty'])) {
                $quoteItem->setQty($params['qty']);
            }

            if(!isset($params['use_custom_price'])) {
                $quoteItem->unsetData('custom_price');
                $quoteItem->unsetData('original_custom_price');
            } elseif (isset($params['custom_price']) && $params['custom_price'] >= 0) {
                $quoteItem->setCustomPrice((float)$params['custom_price']);
                $quoteItem->setOriginalCustomPrice((float)$params['custom_price']);
            }

            if (isset($params['instruction'])) {
                $quoteItem->setData('instruction', trim($params['instruction']));
            }

            $noDiscount = !isset($params['use_discount']);
            $quoteItem->setNoDiscount($noDiscount);

            $quoteItem->save();
        }

        return $this;
    }

    /**
     * Apply newly added products to quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $data
     * @return $this
     */
    public function addNewItems(Mage_Sales_Model_Quote $quote, $data)
    {
        foreach ($data as $productId => $params) {

            if(isset($params['product_id'])) {
                $product = Mage::getModel('catalog/product')->setStoreId($quote->getStoreId())->load($params['product_id']);
            } else {
                $product = Mage::getModel('catalog/product')->setStoreId($quote->getStoreId())->load($productId);
            }
            if (!$product || !$product->getId()) {
                continue;
            }

            if (!isset($params['product'])) {
                $params['product'] = $product->getId();
            }

            $productParams = new Varien_Object($params);
            if ($this->isItemWithParamsExist($quote, $product, $productParams)) {
                continue;
            }

            $quoteItem = $quote->addProduct($product, $productParams);
            if (!is_object($quoteItem)) {
                Mage::throwException($quoteItem);
            }
        }

        if (Mage::registry('ordersedit_order')) {
            Mage::helper('mageworx_ordersedit/edit')->addPendingChanges(Mage::registry('ordersedit_order')->getId(), array(
                    'product_to_add' => array()
                )
            );
        }

        return $this;
    }

    /**
     * Skip new item, if configurable with the same custom options already exists.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param Mage_Catalog_Model_Product $product
     * @param null|float|Varien_Object $request
     *
     * @return boolean
     */
    protected function isItemWithParamsExist($quote, $product, $request)
    {
        if ($request === null) {
            $request = 1;
        }
        if (is_numeric($request)) {
            $request = new Varien_Object(array('qty'=>$request));
        }
        if (!($request instanceof Varien_Object)) {
            Mage::throwException(Mage::helper('sales')->__('Invalid request for adding product to quote.'));
        }

        $processMode = Mage_Catalog_Model_Product_Type_Abstract::PROCESS_MODE_FULL;
        $product->getTypeInstance(true)->prepareForCartAdvanced($request, $product, $processMode);

        return (bool)$quote->getItemByProduct($product);
    }

    /**
     * Set new coupon code to quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param                        $couponCode string | null
     *
     * @return $this
     */
    public function setCouponCode($quote, $couponCode = '')
    {
        $quote->setCouponCode($couponCode);

        return $this;
    }

    /**
     * Validate current coupon code
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string                 $couponCode
     *
     * @return bool
     */
    protected function validateCouponCode($quote, $couponCode = '')
    {
        $codeLength = strlen($couponCode);
        $isCodeLengthValid = $codeLength && $codeLength <= 255;

        // Validate NEW coupon
        if ($codeLength) {
            if ($isCodeLengthValid && $couponCode == $quote->getCouponCode()) {
                Mage::getSingleton('adminhtml/session')->setCouponMessage(
                    Mage::helper('checkout/cart')
                        ->__('Coupon code "%s" was applied.', Mage::helper('core')->escapeHtml($couponCode))
                );

                return true;
            } else {
                // If NEW coupon is not valid add error message
                Mage::getSingleton('adminhtml/session')->setCouponMessage(
                    Mage::helper('checkout/cart')
                        ->__('Coupon code "%s" is not valid.', Mage::helper('core')->escapeHtml($couponCode))
                );
                Mage::helper('mageworx_ordersedit/edit')->addPendingChanges(Mage::registry('ordersedit_order')->getEntityId(),
                    array('coupon_code' => ''));

                return false; // reset coupon code to empty
            }
        } else {
            Mage::getSingleton('adminhtml/session')->setCouponMessage(Mage::helper('checkout/cart')
                ->__('Coupon code was canceled.'));
            $quote->setCouponCode('');

            return true;
        }
    }

    /**
     * Save temp items in quote with "is_temporary" flag
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param int                    $flag
     * @param bool                   $checkItemId
     */
    public function saveTemporaryItems(Mage_Sales_Model_Quote $quote, $flag = 0, $checkItemId = false)
    {
        foreach ($quote->getAllItems() as $item) {
            if ($item->getId() && $checkItemId) {
                continue;
            }
            $item->setData('ordersedit_is_temporary', $flag)->save();
        }
    }

    protected function collectMultifees()
    {
        if (!MageWorx_OrdersEdit_Helper_Data::foeModuleCheck('MageWorx_MultiFees')) {
            return;
        }

        $order = Mage::registry('ordersedit_order');
        if ($order) {
            $feesPost = $this->convertOrdersToFeeSubmitPost($order->getDetailsMultifees());
            Mage::helper('mageworx_multifees')->addFeesToCart($feesPost, $order->getStoreId(), true, 0, 0);
        }
    }

    /**
     * @param $feesData
     * @return array|mixed
     */
    protected function convertOrdersToFeeSubmitPost($feesData) {
        if ($feesData) {
            $feesData = unserialize($feesData);
        } else {
            $feesData = array();
        }
        foreach ($feesData as $feeId => $data) {
            if (!isset($data['options'])) {
                continue;
            }
            foreach ($data['options'] as $optionId=>$value) {
                $feesData[$feeId]['options'][$optionId] = $optionId;
            }
        }
        return $feesData;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     */
    protected function clearQuote(Mage_Sales_Model_Quote $quote)
    {
        $items = $quote->getAllItems();

        /** @var Mage_Sales_Model_Quote_Item $item */
        foreach ($items as $item) {
            if ($this->clearQuoteItems($item, false)) {
                $quote->removeItem($item->getId());
            }
        }
    }

    /** Return true for non valid items
     *
     * @param      $item
     * @param bool $checkChild
     * @return bool
     */
    public function clearQuoteItems($item, $checkChild = false)
    {
        if ($item->getParentItem() && $checkChild) {
            return true;
        }

        $orderItem = $this->getOrderItemByQuoteItem($item);
        if (!$orderItem) {
            return false;
        }

        $parentItem = $orderItem->getParentItem();
        if ($parentItem && $parentItem->getProduct()->getTypeId() != 'configurable') {
            $orderItemAvailableQty = $orderItem->getQtyToCancel() + $orderItem->getQtyToRefund();
            $orderItemQty = $orderItem->getQtyOrdered();
            if ($orderItemAvailableQty < 0.001 && $orderItemQty == $item->getQty() && !count($item->getChildren()) && !$item->getParentItemId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Mage_Sales_Model_Quote_Item $item
     * @return Mage_Sales_Model_Order_Item | null
     */
    protected function getOrderItemByQuoteItem(Mage_Sales_Model_Quote_Item $item)
    {
        if (empty($this->_orderItems)) {
            $order = Mage::registry('ordersedit_order');
            if ($order instanceof Mage_Sales_Model_Order) {
                $orderItemsCollection = $order->getItemsCollection(array(), true);
                $this->_orderItems = $orderItemsCollection->getItems();
            }
        }

        foreach ($this->_orderItems as $orderItem) {
            if ($orderItem->getQuoteItemId() == $item->getItemId()) {
                return $orderItem;
            }
        }

        return null;
    }
}
