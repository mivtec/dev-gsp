<?php

class Fyndiq_Fyndiq_Model_Resource_Setting_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct()
    {
        $this->_init('fyndiq/setting');
    }
}
