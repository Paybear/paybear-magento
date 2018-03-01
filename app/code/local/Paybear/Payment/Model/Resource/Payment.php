<?php

class Paybear_Payment_Model_Resource_Payment extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct()
    {
        $this->_init('paybear/payment', 'paybear_id');
    }
}
