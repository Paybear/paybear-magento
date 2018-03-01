<?php

class Paybear_Payment_Block_Form_Paybear extends Mage_Payment_Block_Form
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('paybear/form/paybear.phtml');
    }
}
