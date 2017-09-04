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
 * @package   Sphinx Search Ultimate
 * @version   2.3.2
 * @build     1242
 * @copyright Copyright (C) 2015 Mirasvit (http://mirasvit.com/)
 */


class Mirasvit_MstCore_Block_Adminhtml_Toolbar extends Mirasvit_MstCore_Block_Adminhtml_Validator
{
    public function isToolbarAllowed()
    {
        return in_array($_SERVER['REMOTE_ADDR'], Mage::helper('mstcore/config')->getDeveloperIp());
    }
}