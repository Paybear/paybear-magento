<?php

class Paybear_Payment_Helper_Data extends Mage_Core_Helper_Abstract {

    const API_DOMAIN                    = 'https://api.paybear.io';
    const API_DOMAIN_TEST               = 'http://test.paybear.io';
    const EMAIL_TEMPLATE_UNDERPAIMENT   = 'paybear_underpayment_email';
    const EMAIL_TEMPLATE_OVERPAIMENT    = 'paybear_overpayment_email';

    public function getApiDomain() {
        $testnet = Mage::getStoreConfig('payment/paybear/testnet');

        if ($testnet) {
            return self::API_DOMAIN_TEST;
        }else {
            return self::API_DOMAIN;
        }
    }
}
