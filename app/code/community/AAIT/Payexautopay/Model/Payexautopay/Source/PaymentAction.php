<?php/** * PayEx Autopay Payment * Created by AAIT Team. */class AAIT_Payexautopay_Model_Payexautopay_Source_PaymentAction{    public function toOptionArray()    {        return array(            array(                'value' => 0,                'label' => Mage::helper('payexautopay')->__('Authorize')            ),            array(                'value' => 1,                'label' => Mage::helper('payexautopay')->__('Sale')            ),        );    }}