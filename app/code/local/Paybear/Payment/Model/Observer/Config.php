<?php

class Paybear_Payment_Model_Observer_Config
{
    public function adminSaveSettingsPaybear($observer)
    {
        $paybear_payment = Mage::getModel('paybear/payment');
        $currencies = $paybear_payment->getCurrencies();

        if (empty($currencies)) {
            $message = '<b> Paybear Payment: </b> 1. Your API Key does not seem to be correct. Get your key at <a href="https://www.paybear.io/" target="_blank"><b>paybear.io</b></a>
2. You do not have any currencies enabled, please enable them to your Merchant Dashboard: <a href="https://www.paybear.io/" target="_blank"><b>paybear.io</b></a>';

            Mage::getConfig()->saveConfig('payment/paybear/active', '0', 'default', 0);
            Mage::getSingleton('adminhtml/session')->addError($message);
        }
    }
}
