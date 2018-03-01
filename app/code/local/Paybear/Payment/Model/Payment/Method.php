<?php

class Paybear_Payment_Model_Payment_Method extends Mage_Payment_Model_Method_Abstract
{
    protected $_code  = 'paybear';
    protected $_formBlockType = 'paybear/form_paybear';
    protected $_isGateway = true;
    // protected $_infoBlockType = 'paybear/info_paybear';

    protected static $_currencies = null;
    protected static $_rates = null;

    // protected function _construct()
    // {
    //     $this->_init('paybear/payment');
    // }

    public function assignData($data)
    {
        $info = $this->getInfoInstance();

        if ($data->getCustomFieldOne())
        {
            $info->setCustomFieldOne($data->getCustomFieldOne());
        }

        if ($data->getCustomFieldTwo())
        {
            $info->setCustomFieldTwo($data->getCustomFieldTwo());
        }

        return $this;
    }

    public function validate()
    {
        parent::validate();
        $info = $this->getInfoInstance();

        return $this;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('paybear/payment');
    }
}
