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

        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template','paybear',array('template' => 'paybear/form.phtml'));
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
            'fiatValue' => (float)$order->getGrandTotal(),
            'currencyIso' => $order->getOrderCurrencyCode(),
            'currencySign' => Mage::app()->getLocale()->currency($order->getOrderCurrencyCode())->getSymbol(),
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

    public function statusAction()
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

        // if ($order->getProtectCode() != $protectCode) {
        //     Mage::throwException($this->__('Order not found.'));
        // }

        $model->load($order->getIncrementId(), 'order_increment_id');

        // $minConfirmations = Configuration::get('PAYBEAR_' . strtoupper($paybearData->token) . '_CONFIRMATIONS');;
        $maxConfirmations = $model->getMaxConfirmations();
        $confirmations = $model->getConfirmations();
        $data = array();
        if ($confirmations >= $maxConfirmations) { //set max confirmations
            $data['success'] = true;
        } else {
            $data['success'] = false;
        }

        if (is_numeric($confirmations)) {
            $data['confirmations'] = $confirmations;
        }

        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($data));
    }

    public function callbackAction()
    {
        $orderId = $this->getRequest()->get('order');
        $protectCode = $this->getRequest()->get('protect_code');
        $order = new Mage_Sales_Model_Order();
        $order->loadByIncrementId($orderId);

        if (!$order->getIncrementId() || $order->getProtectCode() != $protectCode) {
            Mage::throwException($this->__('Order not found.'));
        }

        $currency = $order->getOrderCurrency();

        $model = Mage::getModel('paybear/payment');
        $model->load($orderId, 'order_increment_id');

        if (!$model->getToken()) {
            Mage::throwException($this->__('Order not found.'));
        }

        // $customer = $order->getCustomer();
        // $sdk = new PayBearSDK($this->context);

        $data = file_get_contents('php://input');

        if (!in_array($order->getStatus(), array(
            Mage::getStoreConfig('payment/paybear/order_status'),
            Mage::getStoreConfig('payment/paybear/awaiting_confirmations_status'),
        ))) {
            return;
        }

        if ($data) {
            $params = json_decode($data);
            $maxConfirmations = $model->getMaxConfirmations();
            $invoice = $params->invoice;

            $model->confirmations = $params->confirmations;
            $model->save();

            // PrestaShopLogger::addLog(sprintf('PayBear: incoming callback. Confirmations - %d', $params->confirmations), 1, null, 'Order', $order->id, true);

            if ($params->confirmations >= $maxConfirmations) {
                $toPay = $model->getAmount();
                $amountPaid = $params->inTransaction->amount / pow(10, $params->inTransaction->exp);
                $maxDifference = 0.00000001;
                // $paybear = Module::getInstanceByName('paybear');

                // PrestaShopLogger::addLog(sprintf('PayBear: to pay %s', $toPay), 1, null, 'Order', $order->id, true);
                // PrestaShopLogger::addLog(sprintf('PayBear: paid %s', $amountPaid), 1, null, 'Order', $order->id, true);
                // PrestaShopLogger::addLog(sprintf('PayBear: maxDifference %s', $maxDifference), 1, null, 'Order', $order->id, true);

                $orderStatus = Mage::getStoreConfig('payment/paybear/mispaid_status'); //Configuration::get('PAYBEAR_OS_MISPAID');
                $message = false;

                if ($toPay > 0 && ($toPay - $amountPaid) < $maxDifference) {
                    $orderTimestamp = strtotime($order->date_add);
                    $paymentTimestamp = strtotime($model->getPaidAt());
                    $deadline = $orderTimestamp + Mage::getStoreConfig('payment/paybear/exchange_locktime') * 60;
                    $orderStatus = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;

                    if ($paymentTimestamp > $deadline) {
                        $orderStatus = Configuration::get('PAYBEAR_OS_LATE_PAYMENT_RATE_CHANGED');

                        $fiatPaid = $amountPaid * $model->getRate($params->blockchain);
                        if ($order->total_paid < $fiatPaid) {
                            $message = sprintf('Late Payment / Rate changed (%s %s paid, %s %s expected)', $fiatPaid, $currency->iso_code, $order->total_paid, $currency->iso_code);
                        } else {
                            $orderStatus = Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW;

                            $order->addStatusHistoryComment($message, $orderStatus);
                            $order->save();
                        }
                    }
                } else {
                    // PrestaShopLogger::addLog(sprintf('PayBear: wrong amount %s', $amountPaid), 2, null, 'Order', $order->id, true);
                    $underpaid = round(($toPay-$amountPaid)*$model->getRate($params->blockchain), 2);
                    $message = sprintf('Wrong Amount Paid (%s %s received, %s %s expected) - %s %s underpaid', $amountPaid, $params->blockchain, $toPay, $params->blockchain, $currency->sign, $underpaid);
                }

                // $order->setState(Mage_Sales_Model_Order::STATE_CANCELED);
                $order->addStatusHistoryComment($message, $orderStatus);
                $order->save();

                echo $invoice; //stop further callbacks
                die();
            } elseif ($order->getStatus() != Mage::getStoreConfig('payment/paybear/awaiting_confirmations_status')) {
                $model->setPaidAt(date('Y-m-d H:i:s'));
                $model->save();

                $order->addStatusHistoryComment('', Mage::getStoreConfig('payment/paybear/awaiting_confirmations_status'));
                $order->save();
            }
        }
        die();
    }
}
