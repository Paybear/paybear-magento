<?php

class Paybear_Payment_Model_Resource_Paymenttxn extends Mage_Core_Model_Resource_Db_Abstract
{

    protected function _construct()
    {
        $this->_init('paybear/paymenttxn', 'id_txn');
    }
}
