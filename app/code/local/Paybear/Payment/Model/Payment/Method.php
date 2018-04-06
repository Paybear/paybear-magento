<?php

class Paybear_Payment_Model_Payment_Method extends Mage_Payment_Model_Method_Abstract
{

    protected $_isGateway = true;

    protected $_code  = 'paybear';
    protected $_formBlockType = 'paybear/form_paybear';

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('paybear/payment');
    }
}
