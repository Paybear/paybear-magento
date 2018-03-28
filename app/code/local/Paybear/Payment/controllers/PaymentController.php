<?php

class Paybear_Payment_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $order = new Mage_Sales_Model_Order();
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        if (!$orderId) {
            Mage::throwException($this->__('Order not found'));
        }

        $order->loadByIncrementId($orderId);

        $this->loadLayout();

        /** @var Paybear_Payment_Model_Payment $model */
        $paybear_payment = Mage::getModel('paybear/payment');
        $currency_sign   = Mage::app()->getLocale()->currency($order->getOrderCurrencyCode())->getSymbol();
        $overpayment     = max(Mage::getStoreConfig('payment/paybear/minoverpaymentfiat'), 0);
        $underpayment    = max(Mage::getStoreConfig('payment/paybear/maxunderpaymentfiat'), 0);

        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','paybear',array('template' => 'paybear/form.phtml'));
        $total_paid = $paybear_payment->getAlreadyPaid($orderId);
        $fiat_value = max((float)$order->getGrandTotal() - $total_paid, 0);

        $payment_status = 'Pending Payment';
        //$payment_status = Mage::getModel('sales/order_status')->load($order->getStatus(), 'status')->getLabel();
        $status = $order->getStatus();
        if ($status == 'complete' || $status == 'processing') {
            $payment_status = 'Paid';
        }

        if ($status == 'awaiting_confirmations' && ($total_paid - $fiat_value) < $underpayment) {
            $payment_status = 'Waiting for Confirmations';
        } elseif ($status == 'mispaid' || ($total_paid > 0 && ($fiat_value > $underpayment) )) {
            $payment_status = 'Partial Payment';
        }

        $order_overview = '<div>Order overview #'.$orderId.' ('.$order->getStatus().')</div>';
        $order_overview .= '<div>Payment status - ' . $payment_status . '</div>';
        $is_overpaid = (($total_paid - $order->getGrandTotal()) > $overpayment) ? true : false;



        if ($paybear_payment->load($orderId, 'order_increment_id')->getPaybearId()) {
            $token = $paybear_payment->getToken();
            if ($token && $total_paid > 0 && $fiat_value > $underpayment && !$is_overpaid ) {
                $address = $paybear_payment->getSavedAddressByToken(strtolower($token), $paybear_payment->getAddress());
                $order_overview .= '<div>Selected token - '.strtoupper($token).'</div>';
                $order_overview .= '<div>Payment address - '.$address.'</div>';
                $order_overview .= '<div>Total to pay - ' . $currency_sign . $fiat_value . '</div>';
                if ($fiat_value > 0) {
                    $order_overview .= '<div>Paid - '. $currency_sign . $total_paid .' </div>';
                    $order_overview .= '<div>Left to pay - ' . $currency_sign . round($fiat_value, 2) . '</div>';
                }
            }
        }

        $block->addData([
            'currencies' => Mage::getUrl('paybear/payment/currencies', [
                'order' => $order->getIncrementId(),
                'protect_code' => $order->getProtectCode()
            ]),
            'status' => Mage::getUrl('paybear/payment/status', [
                'order' => $order->getIncrementId(),
                'protect_code' => $order->getProtectCode()
            ]),
            'redirect' => Mage::getUrl('checkout/onepage/success'),
            //'fiat_value' => (float)$order->getGrandTotal(),
            'fiat_value' => $fiat_value,
            'currency_iso' => $order->getOrderCurrencyCode(),
            'currency_sign' => $currency_sign,
            'overpayment' => $overpayment,
            'underpayment' => $underpayment,
            'order_overview' => $order_overview
        ]);

        $this->getLayout()->getBlock('head')->addCss('css/paybear.css');
        $this->getLayout()->getBlock('head')->addJs('paybear/paybear.js');
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    public function currenciesAction()
    {
        $orderId = $this->getRequest()->get('order');

        if (!$orderId) {
            Mage::throwException($this->__('Order not found'));
        }

        $protectCode = $this->getRequest()->get('protect_code');

        $order = new Mage_Sales_Model_Order();
        $order->loadByIncrementId($orderId);

        /** @var Paybear_Payment_Model_Payment $model */
        $model = Mage::getModel('paybear/payment');

        if ($order->getProtectCode() != $protectCode) {
            Mage::throwException($this->__('Order not found.'));
        }

        if ($this->getRequest()->get('token')) {
            $data = $model->getCurrency($this->getRequest()->get('token'), $orderId, $protectCode, true);
        } else {
            $data = [];
            $currencies = $model->getCurrencies();
            foreach ($currencies as $token => $currency) {
                $currency = $model->getCurrency($token, $orderId, $protectCode);
                if ($currency) {
                    $data[] = $currency;
                }
            }
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
    }

    public function statusAction () {

        $orderId = $this->getRequest()->get('order');

        if (!$orderId) {
            Mage::throwException($this->__('Order not found'));
        }

        $order = new Mage_Sales_Model_Order();
        $order->loadByIncrementId($orderId);

        $paybear_payment = Mage::getModel('paybear/payment');
        $paybear_payment->load($order->getIncrementId(), 'order_increment_id');

        $payment_txn = Mage::getModel('paybear/paymenttxn');

        $maxConfirmations = $paybear_payment->getMaxConfirmations();
        $data = array();

        $totalConfirmations = $payment_txn->getTxnConfirmations($orderId);
        $totalConfirmed     = $payment_txn->getTotalConfirmed($orderId, $maxConfirmations);

        if (($totalConfirmations >= $maxConfirmations) && ($totalConfirmed >= $paybear_payment->amount )) { //set max confirmations
            $data['success'] = true;
        } else {
            $data['success'] = false;
        }

        if (is_numeric($totalConfirmations)) {
            $data['confirmations'] = $totalConfirmations;
        }

        $coinsPaid = $payment_txn->getTotalPaid($orderId);

        if ($coinsPaid) {
            $data['coinsPaid'] = $coinsPaid;

            $underpayment = $paybear_payment->getAmount() - $coinsPaid;
            $email_flag   =  $paybear_payment->getEmailStatus();
            if (($underpayment > 0) && (empty($email_flag))) {

                if ($paybear_payment->sendEmail('underpayment', $underpayment, round($underpayment * $paybear_payment->getRate($paybear_payment->getToken()),2), $paybear_payment->getToken(), $order )) {
                    $paybear_payment->setEmailStatus(1);
                    $paybear_payment->save();
                }
            }
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
    }

    public function callbackAction () {
        $orderId = $this->getRequest()->getParam('order');


        $order = new Mage_Sales_Model_Order();
        $order->loadByIncrementId($orderId);

        if (!$order->getIncrementId()) {
            Mage::throwException($this->__('Order not found.'));
        }

        $currency = $order->getOrderCurrency();

        $paybear_payment = Mage::getModel('paybear/payment');
        $paybear_payment = $paybear_payment->load($orderId, 'order_increment_id');

        if (!$paybear_payment->getToken()) {
            Mage::throwException($this->__('Order not found.'));
        }

        $payment_txn = Mage::getModel('paybear/paymenttxn');

        if (!in_array($order->getStatus(), array(
            Mage::getStoreConfig('payment/paybear/order_status'),
            Mage::getStoreConfig('payment/paybear/awaiting_confirmations_status'),
            Mage::getStoreConfig('payment/paybear/mispaid_status')
        ))) {
            return;
        }

        $data = file_get_contents('php://input');

        if ($data) {

            $params = json_decode($data);



            $maxConfirmations = $paybear_payment->getMaxConfirmations();
            $invoice = $params->invoice;

            $maxDifference_fiat = Mage::getStoreConfig('payment/paybear/maxunderpaymentfiat');
            $maxDifference_coins = 0;

            if($maxDifference_fiat) {
                $maxDifference_coins = round($maxDifference_fiat/$paybear_payment->getRate($params->blockchain) , 8);
                $maxDifference_coins = max($maxDifference_coins, 0.00000001);
            }

            if ($params->invoice == $paybear_payment->invoice) {

                $paybear_payment->setTxn($params, $paybear_payment->getPaybearId());


                $hash = $params->inTransaction->hash;

                $confirmations = $paybear_payment->confirmations;

                if (!$confirmations) {
                    $confirmations = array();
                } else {
                    $confirmations = json_decode($confirmations, true);
                }

                $confirmations[$hash] = $params->confirmations;

                $paybear_payment->confirmations = json_encode($confirmations);
                $paybear_payment->save();

                $toPay = $paybear_payment->getAmount();

                $amountPaid = $params->inTransaction->amount / pow(10, $params->inTransaction->exp);

                $totalConfirmations = $payment_txn->getTxnConfirmations($orderId);

                if ($totalConfirmations >= $maxConfirmations) {

                    //avoid race conditions
                    $transactionIndex = array_search($hash, array_keys($confirmations));
                    if ($transactionIndex>0) sleep($transactionIndex*1);

                    $totalConfirmed =  $payment_txn->getTotalConfirmed($orderId, $maxConfirmations);


                    if (($toPay > 0 && ($toPay - $amountPaid) < $maxDifference_coins)  || (($toPay - $totalConfirmed) < $maxDifference_coins ) ) {

                        $orderTimestamp   = strtotime($order->getData('created_at'));
                        $paymentTimestamp = strtotime($paybear_payment->getPaidAt());
                        $deadline         = $orderTimestamp + Mage::getStoreConfig('payment/paybear/exchange_locktime') * 60;

                        if ($paymentTimestamp > $deadline) {
                            $orderStatus = Mage::getStoreConfig('payment/paybear/late_payment_status');

                            $fiatPaid = $totalConfirmed * $paybear_payment->getRate($params->blockchain);
                            if ((float) $fiatPaid < $order->getData('grand_total')) {
                                $message = sprintf('Late Payment / Rate changed (%s %s paid, %s %s expected)', round($fiatPaid,2), $currency->getData('currency_code'), $order->getData('grand_total'), $currency->getData('currency_code'));
                                $order->addStatusHistoryComment($message, $orderStatus);
                                $order->save();
                            }
                        }

                        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                        $message = sprintf('Amount Paid: %s, Blockchain: %s. ', $totalConfirmed, $params->blockchain);

                        $history = $order->addStatusHistoryComment($message, Mage_Sales_Model_Order::STATE_PROCESSING);

                        $history->setIsCustomerNotified(false);
                        $order->save();

                        //check overpaid
                        $minoverpaid = Mage::getStoreConfig('payment/paybear/minoverpaymentfiat');
                        $overpaid    =  (round(($totalConfirmed - $toPay)*$paybear_payment->getRate($params->blockchain), 2));
                        if ( ($minoverpaid > 0) && ($overpaid > $minoverpaid) ) {

                            if ($paybear_payment->sendEmail('overpayment', $totalConfirmed - $toPay, $overpaid, $paybear_payment->getToken(), $order )) {
                                $history = $order->addStatusHistoryComment('Looks as customer has overpaid an order');
                                $history->setIsCustomerNotified(true);
                                $paybear_payment->setEmailStatus(1);
                                $paybear_payment->save();
                            }

                            $order->save();
                        }

                        echo $invoice; //stop further callbacks
                        return;

                    } else {

                        $orderStatus = Mage::getStoreConfig('payment/paybear/mispaid_status');
                        $underpaid = round(($toPay- $totalConfirmed)*$paybear_payment->getRate($params->blockchain), 2);
                        $message = sprintf('Wrong Amount Paid (%s %s is received, %s %s is expected) - %s %s is underpaid', $amountPaid, $params->blockchain, $toPay, $params->blockchain, $currency->getData('currency_code'), $underpaid);
                        $order->addStatusHistoryComment($message, $orderStatus);
                        $order->save();
                    }

                } else {
                    $unconfirmedTotal = $payment_txn->getTotalUnconfirmed($orderId, $maxConfirmations);

                    $paybear_payment->setPaidAt(date('Y-m-d H:i:s'));
                    $paybear_payment->save();

                    $massage = sprintf('%s Awaiting confirmation. Total Unconfirmed: %s %s', date('Y-m-d H:i:s'), $unconfirmedTotal, $params->blockchain );
                    $order->addStatusHistoryComment($massage, Mage::getStoreConfig('payment/paybear/awaiting_confirmations_status'));
                    $order->save();

                }
            }
        }

        return;
    }

}