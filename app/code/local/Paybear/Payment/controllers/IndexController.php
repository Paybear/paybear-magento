<?php

class Paybear_Payment_IndexController extends Mage_Core_Controller_Front_Action
{
    public function testModelAction()
    {
        $payment = Mage::getModel('paybear/payment');
        echo get_class($payment);
    }
}
