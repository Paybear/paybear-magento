<?php

class Paybear_Payment_Model_Observer_Config
{
    public function adminSaveSettingsPaybear($observer)
    {
        $paybear_payment = Mage::getModel('paybear/payment');
        $response = $paybear_payment->checkPayBearResponse();
        $message = '';

        if (empty($response)) {
            $message = "Unable to connect to PayBear. Please check your network or contact support.";
        }

        if ($response['success'] === false ) {
            $message = '<b> Paybear Payment: </b> Your API Key does not seem to be correct. Get your key at <a href="https://www.paybear.io/" target="_blank"><b>PayBear.io</b></a>';
        }

        if ($response['success'] === true && empty($response['data'])) {
            $message = '<b> Paybear Payment: </b> You do not have any currencies enabled, please enable them to your Merchant Dashboard: <a href="https://www.paybear.io/" target="_blank"><b>PayBear.io</b></a>';
        }

        if ($message) {
            Mage::getConfig()->saveConfig('payment/paybear/active', '0', 'default', 0);
            Mage::getSingleton('adminhtml/session')->addError($message);
        }
    }
}
