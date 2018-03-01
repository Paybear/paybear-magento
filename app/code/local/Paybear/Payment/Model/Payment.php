<?php

class Paybear_Payment_Model_Payment extends Mage_Core_Model_Abstract
{
    protected static $_currencies = null;
    protected static $_rates = null;

    protected function _construct()
    {
        $this->_init('paybear/payment');
    }

    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('paybear/payment');
    }

    public function getCurrencies()
    {
        if (self::$_currencies === null) {
            $url = sprintf('http://s.etherbill.io/v2/currencies?token=%s', Mage::getStoreConfig('payment/paybear/api_secret'));
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            self::$_currencies = $data['data'];
        }

        return self::$_currencies;
    }

    public function getCurrency($token, $orderId, $protectCode, $getAddress = false)
    {
        $rate = $this->getRate($token);

        if ($rate) {
            $order = new Mage_Sales_Model_Order();
            $order->loadByIncrementId($orderId);
            $fiatValue = (float)$order->getGrandTotal();
            $coinsValue = round($fiatValue / $rate, 8);

            $currencies = $this->getCurrencies();
            $currency = (object) $currencies[strtolower($token)];
            $currency->coinsValue = $coinsValue;
            $currency->rate = round($currency->rate, 2);


            if ($getAddress) {
                $currency->address = $this->getTokenAddress($orderId, $protectCode, $token);
            } else {
                $currency->currencyUrl = Mage::getUrl('paybear/payment/currencies', [
                    'token' => $token,
                    'order' => $orderId,
                    'protect_code' => $protectCode
                ]);
            }

            return $currency;

        }

        // echo 'can\'t get rate for ' . $token;

        return null;
    }

    public function getRate($curCode)
    {
        $rates = $this->getRates();
        $curCode = strtolower($curCode);

        return isset($rates->$curCode) ? $rates->$curCode->mid : false;
    }

    public function getRates()
    {
        if (self::$_rates === null) {
            // $currency = $this->context->currency;
            $url = sprintf("http://s.etherbill.io/v2/exchange/%s/rate", 'usd');
            // $url = sprintf("http://s.etherbill.io/v2/exchange/%s/rate", strtolower($currency->iso_code));

            if ($response = file_get_contents($url)) {
                $response = json_decode($response);
                if ($response->success) {
                    self::$_rates = $response->data;
                }
            }
        }

        return self::$_rates;
    }

    public function getTokenAddress($orderId, $protectCode, $token = 'ETH')
    {
        $data = Mage::getModel('paybear/payment')->load($orderId, 'order_increment_id');
        $order = new Mage_Sales_Model_Order();
        $order->loadByIncrementId($orderId);

        $apiSecret = Mage::getStoreConfig('payment/paybear/api_secret');
        $currencies = $this->getCurrencies();
        $rate = $this->getRate($token);
        $fiatAmount = $order->getGrandTotal();
        $coinsAmount = round($fiatAmount / $rate, 8);

        if ($data->getOrderIncrementId() && strtolower($data->getToken()) == strtolower($token)) {
            $data->setAmount($coinsAmount);
            $data->setUpdatedAt(date('Y-m-d H:i:s'));
            $data->save();

            return $data->getAddress();
        } elseif (!$data->getOrderIncrementId()) {
            $data->setOrderIncrementId($orderId);
            $data->setCreatedAt(date('Y-m-d H:i:s'));
        }

        $callbackUrl = Mage::getUrl('paybear/payment/callback', [
            'order' => $orderId,
            'protect_code' => $protectCode
        ]);

        $url = sprintf('http://s.etherbill.io/v2/%s/payment/%s?token=%s', strtolower($token), urlencode($callbackUrl), $apiSecret);
        if ($response = file_get_contents($url)) {
            $response = json_decode($response);

            if (isset($response->data->address)) {
                $data->setConfirmations(null);
                $data->setToken(strtolower($token));
                $data->setAddress($response->data->address);
                $data->setInvoice($response->data->invoice);
                $data->setAmount($coinsAmount);
                $data->setMaxConfirmations($currencies[strtolower($token)]['maxConfirmations']);
                $data->setUpdatedAt(date('Y-m-d H:i:s'));
                $data->save();

                return $response->data->address;
            }
        }

        return null;
    }
}
