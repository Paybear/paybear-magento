<?php

$installer = $this;

$installer->startSetup();

/* Email templates  */

/* Create email template if does not exist */
$templateId = Paybear_Payment_Helper_Data::EMAIL_TEMPLATE_UNDERPAIMENT;
$emailTemplate = Mage::getModel('core/email_template')->loadByCode($templateId);

if(!$emailTemplate->getId()){
    $sql = "INSERT INTO {$this->getTable('core_email_template')} (template_code, template_text, template_type, template_subject, template_sender_name, template_sender_email, added_at, modified_at) VALUES
    ('$templateId', '<body style=\"background:#FFFFFF; font-family:Verdana, Arial, Helvetica, sans-serif; font-size:12px; margin:0; padding:0;\">
<div style=\"background:#FFFFFF; font-family:Verdana, Arial, Helvetica, sans-serif; font-size:12px; margin:0; padding:0;\">
<p>Looks like you underpaid {{var cryptopaid}} {{var token}},</p>
<p>Don\'t worry, here is what to do next:</p>

<p>Contact the merchant directly and<p>
<p>-Request details on how you can pay the difference.</p>
<p>-Request a refund and create a new order.</p>


<p>Tips for Paying with Crypto:</p>

<p>Tip 1) When paying, ensure you send the correct amount in {{var fiat_currency}}. Do not manually enter the {{var fiat_currency}} Value. </p>

<p>Tip 2)  If you are sending from an exchange, be sure to correctly factor in their withdrawal fees.</p>

<p>Tip 3) Be sure to successfully send your payment before the countdown timer expires. This timer is setup to lock in a fixed rate for your payment. Once it expires, rates may change.</p>
</div>
</body>', 2, '{{var store.getFrontendName()}}: Order {{htmlescape var=\$order.getIncrementId()}}: Important Note', NULL, NULL, NOW(), NOW());";

    $installer->run($sql);
}

$installer->endSetup();