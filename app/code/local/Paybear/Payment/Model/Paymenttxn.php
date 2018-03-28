<?php

class Paybear_Payment_Model_Paymenttxn extends Mage_Core_Model_Abstract
{

    protected function _construct()
    {
        $this->_init('paybear/paymenttxn');
    }

    public function getTxnConfirmations($order_id) {

        $txns = $this->getCollection()
            ->addFieldToFilter('order_id', $order_id);
        $confirmations = array();
        if ($txns->getSize() > 0)
            foreach ($txns as $txn) {
                $confirmations[] = $txn->confirmations;
            }

        return (count($confirmations)) ? min($confirmations) : null ;
    }

    public function getTotalConfirmed($order_id, $maxConfirmations) {

        $txns = $this->getCollection()
            ->addFieldToFilter('order_id', $order_id);
        $totalConfirmed = 0;
        if ($txns->getSize() > 0)
            foreach ($txns as $txn) {
                if ($txn->getConfirmations() >= $maxConfirmations) {
                    $totalConfirmed += $txn->getTxnAmount();
                }
            }

        return $totalConfirmed;
    }

    public function getTotalUnconfirmed($order_id, $maxConfirmations) {
        $txns = $this->getCollection()
            ->addFieldToFilter('order_id', $order_id);
        $totalUnConfirmed = 0;
        if ($txns->getSize() > 0)
            foreach ($txns as $txn) {
                if ($txn->getConfirmations() < $maxConfirmations) {
                    $totalUnConfirmed += $txn->getTxnAmount();
                }
            }

        return $totalUnConfirmed;
    }

    public function getTotalPaid($order_id) {
        $txns = $this->getCollection()
            ->addFieldToFilter('order_id', $order_id);
        $total = 0;
        if ($txns->getSize() > 0)
            foreach ($txns as $txn) {
                $total += $txn->getTxnAmount();
            }

        return $total;
    }

}
