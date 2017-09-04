<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at http://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   RMA
 * @version   2.0.1
 * @build     982
 * @copyright Copyright (C) 2015 Mirasvit (http://mirasvit.com/)
 */



class Mirasvit_Rma_Block_Rma_Guest_View extends Mirasvit_Rma_Block_Rma_View
{
    public function getCommentPostUrl()
    {
        return Mage::getUrl('rma/guest/savecomment');
    }

    public function getId()
    {
        return $this->getRma()->getGuestId();
    }

    public function getConfirmationUrl()
    {
        return Mage::getUrl('rma/guest/savecomment', array('id' => $this->getRma()->getGuestId(), 'shipping_confirmation' => true));
    }

    public function getListUrl()
    {
        return Mage::getUrl('rma/guest/list');
    }
}
