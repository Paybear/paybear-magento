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
            $url = Mage::helper('paybear')->getApiDomain() . sprintf('/v2/currencies?token=%s', Mage::getStoreConfig('payment/paybear/api_secret'));
            $response = file_get_contents($url);
            $data = json_decode($response, true);

            self::$_currencies = $data['data'];
        }

        return self::$_currencies;
    }

    public function checkPayBearResponse() {

        $url = Mage::helper('paybear')->getApiDomain() . sprintf('/v2/currencies?token=%s', Mage::getStoreConfig('payment/paybear/api_secret'));
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        return $data;
    }

    public function getCurrency($token, $orderId, $getAddress = false)
    {
        $token = $this->sanitize_token($token);
        $rate = $this->getRate($token);

        if ($rate) {
            $order = new Mage_Sales_Model_Order();
            $order->loadByIncrementId($orderId);
            $fiatValue = (float)$order->getGrandTotal();

            /*if ($already_paid = $this->getAlreadyPaid($orderId)) {
                $fiatValue = $fiatValue - $already_paid;
            }*/

            $coinsValue = round($fiatValue / $rate, 8);

            $currencies = $this->getCurrencies();
            $currency = (object) $currencies[strtolower($token)];

            if ( $coinsValue >= $currency->minimum ) {
                $currency->coinsValue = $coinsValue;

                $currency->rate = round($rate, 2);
                $coinsPaid = $this->getAlreadyPaidCoins($orderId);
                $currency->coinsPaid = round($coinsPaid, 8);

                if ($getAddress) {
                    $currency->address = $this->getTokenAddress($orderId, $token);
                } else {
                    $currency->currencyUrl = Mage::getUrl('paybear/payment/currencies', [
                        'token' => $token,
                        'order' => $orderId
                    ]);
                }

                return $currency;
            }

        }

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
            $currency = strtolower(Mage::app()->getStore()->getCurrentCurrencyCode());
            $url = Mage::helper('paybear')->getApiDomain() . sprintf("/v2/exchange/%s/rate", $currency);

            if ($response = file_get_contents($url)) {
                $response = json_decode($response);
                if ($response->success) {
                    self::$_rates = $response->data;
                }
            }
        }

        return self::$_rates;
    }

    public function getTokenAddress($orderId, $token)
    {
        $data = Mage::getModel('paybear/payment')->load($orderId, 'order_increment_id');
        $order = new Mage_Sales_Model_Order();
        $order->loadByIncrementId($orderId);

        $apiSecret = Mage::getStoreConfig('payment/paybear/api_secret');
        $currencies = $this->getCurrencies();
        $token = $this->sanitize_token($token);
        $rate = $this->getRate($token);
        $fiatAmount = $order->getGrandTotal();
        $coinsAmount = round($fiatAmount / $rate, 8);

        $address = $this->getSavedAddressByToken(strtolower($token), $data->getAddress());

        if ($data->getOrderIncrementId() && $address) {
            $data->setConfirmations(null);
            $data->setToken(strtolower($token));
            $data->setAmount($coinsAmount);
            $data->setMaxConfirmations($currencies[strtolower($token)]['maxConfirmations']);
            $data->setUpdatedAt(date('Y-m-d H:i:s'));
            $data->save();

            return $address;
        } elseif (!$data->getOrderIncrementId()) {
            $data->setOrderIncrementId($orderId);
            $data->setCreatedAt(date('Y-m-d H:i:s'));
        }

        $callbackUrl = Mage::getUrl('paybear/payment/callback', [
            'order' => $orderId
        ]);

        $url = Mage::helper('paybear')->getApiDomain() . sprintf('/v2/%s/payment/%s?token=%s', strtolower($token), urlencode($callbackUrl), $apiSecret);
        if ($response = file_get_contents($url)) {
            $response = json_decode($response);

            if (isset($response->data->address)) {
                $address = (is_array(json_decode($data->getAddress(), true))) ? json_decode($data->getAddress(), true) : array() ;
                $data->setConfirmations(null);
                $data->setToken(strtolower($token));
                $address = array_merge($address, array(strtolower($token)=>$response->data->address));
                $data->setAddress(json_encode($address));

                $invoice = (is_array(json_decode($data->getInvoice(), true))) ? json_decode($data->getInvoice(), true) : array();
                $invoice = array_merge($invoice, array(strtolower($token)=>$response->data->invoice));
                //$data->setInvoice($response->data->invoice);
                $data->setInvoice(json_encode($invoice));

                $data->setAmount($coinsAmount);
                $data->setMaxConfirmations($currencies[strtolower($token)]['maxConfirmations']);
                $data->setUpdatedAt(date('Y-m-d H:i:s'));
                $data->save();

                return $response->data->address;
            }
        }

        return null;
    }

    public function getSavedAddressByToken($token, $address) {
        try {
            if ($address) {
                $address = json_decode($address, true);

                if (array_key_exists($token, $address))
                    return  $address[$token];
            }

        }  catch (Exception $e) {
            Mage::logException($e);
        }

        return null;
    }

    public function getInvoiceByToken($token, $invoice) {
        try {
            if ($invoice) {
                $invoice = json_decode($invoice, true);

                if (array_key_exists($token, $invoice))
                    return  $invoice[$token];
            }

        }  catch (Exception $e) {
            Mage::logException($e);
        }

        return null;
    }

    public function getBlockExplorerUrl($token, $address) {
        $currencies = $this->getCurrencies();
        foreach ($currencies as $token_code => $currency) {
            if ($token_code == $token) {
                return sprintf($currency['blockExplorer'], $address);
            }
        }
    }

    public function getAlreadyPaid($orderId) {
        try {
            $paybear_payment = Mage::getModel('paybear/payment')->load($orderId, 'order_increment_id');
            if ($paybear_payment->getPaybearId()) {
                $token = $paybear_payment->getToken();
                $rate = $this->getRate($token);
                $already_paid = Mage::getModel('paybear/paymenttxn')->getTotalPaid($orderId);
                return round($already_paid*$rate, 2);
            }

        } catch (Exception $e) {

        }

        return 0;
    }

    public function getAlreadyPaidCoins ($orderId) {
        try {
            $paybear_payment = Mage::getModel('paybear/payment')->load($orderId, 'order_increment_id');
            if ($paybear_payment->getPaybearId()) {

                $already_paid = Mage::getModel('paybear/paymenttxn')->getTotalPaid($orderId);
                return $already_paid;
            }

        } catch (Exception $e) {

        }

        return 0;

    }

    public function createInvoice($order_id) {
        $order = Mage::getModel("sales/order")->loadByIncrementId($order_id);
        try {
            if(!$order->canInvoice())
            {
                Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
            }

            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            if (!$invoice->getTotalQty()) {
                Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
            }

            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->register();
            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transactionSave->save();

            $invoice->setRequestedCaptureCase(
                Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE
            );
        }
        catch (Mage_Core_Exception $e) {

        }



    }

    public function sendEmail($subject = 'underpayment', $paidCrypto, $fiat_paid, $token, $order)
    {
        $senderName     = Mage::getStoreConfig('trans_email/ident_general/name');
        $senderEmail    = Mage::getStoreConfig('trans_email/ident_general/email');

        $customerName   = $order->getCustomerName();
        $customerEmail  = $order->getCustomerEmail();

        $token = $this->sanitize_token($token);

        $fiat_currency = strtoupper($order->getOrderCurrencyCode());

        $vars = array(
            'order'         => $order,
            'store'         => Mage::app()->getStore(),
            'order_id'      => $order->getIncrementId(),
            'cryptopaid'    => $paidCrypto,
            'fiat_paid'     => $fiat_paid,
            'token'         => strtoupper( $token ),
            'fiat_currency' => $fiat_currency
        );

        $templateId = Paybear_Payment_Helper_Data::EMAIL_TEMPLATE_UNDERPAIMENT;
        if ($subject == 'overpayment') {
            $templateId = Paybear_Payment_Helper_Data::EMAIL_TEMPLATE_OVERPAIMENT;
        }

        try {

            $emailTemplate = Mage::getModel('core/email_template')->loadByCode($templateId);
            $emailTemplate->getProcessedTemplate($vars);
            $emailTemplate->setSenderEmail($senderEmail);
            $emailTemplate->setSenderName($senderName);
            $emailTemplate->send($customerEmail, $customerName, $vars);

            return 1;
        } catch(Exception $e) {
            Mage::logException($e);
        }

        return 0;
    }

    public function setTxn ($params, $paybear_payment_id) {

        /** @var Paybear_Payment_Model_Paymenttxn $model */
        $paybear_txn = Mage::getModel('paybear/paymenttxn');

        /** @var Paybear_Payment_Model_Payment $model */
        $paybear_payment = $this->load($paybear_payment_id, 'paybear_id');

        $txn_hash       = $params->inTransaction->hash;

        $token = $this->sanitize_token($params->blockchain);
        $address        = $this->getSavedAddressByToken($token, $paybear_payment->getAddress());

        $txn_amount     = $params->inTransaction->amount / pow(10, $params->inTransaction->exp);

        $order_amount   = $paybear_payment->getAmount();
        $invoice        = $params->invoice;
        $order_id       = $paybear_payment->getOrderIncrementId();

        $confirmations  = $params->confirmations;

        $paybear_txn->load($txn_hash, 'txn');

        if ($paybear_txn->getIdTxn()) {
            $paybear_txn->setConfirmations($confirmations);
            $paybear_txn->setUpdatedAt(date('Y-m-d H:i:s'));
        }else{
            $paybear_txn->setTxn($txn_hash);
            $paybear_txn->setToken($token);
            $paybear_txn->setAddress($address);
            $paybear_txn->setTxnAmount($txn_amount);
            $paybear_txn->setOrderAmount($order_amount);
            $paybear_txn->setInvoice($invoice);
            $paybear_txn->setOrderId($order_id);
            $paybear_txn->setConfirmations($confirmations);
            $paybear_txn->setCreatedAt(date('Y-m-d H:i:s'));
        }

        $paybear_txn->save();

    }

    public function sanitize_token( $token ) {
        $token = strtolower($token);
        $token = preg_replace('/[^a-z0-9:]/', '', $token);
        return $token;
    }

    public function getCurrenciesIconsHtml($size = 5) {

        $currencies = array_slice($this->getCurrencies(), 0, $size);
        $_html = '';

        if (count($currencies) > 0) {
            $_html .= '<span class="payment_icons" />';
            foreach ($currencies as $code =>$currency) {
                $_html .= sprintf('<img src="%s" alt="%s" width="%spx" height="%spx" />', $currency['icon'], $currency['code'], 30, 30 );
            }
            $_html .= '</span>';
        }

        return $_html;
    }

}
