<?php

$installer = $this;

$installer->startSetup();

/* Email templates  */

/* Create email template if does not exist */
$templateId = Paybear_Payment_Helper_Data::EMAIL_TEMPLATE_OVERPAIMENT;
$emailTemplate = Mage::getModel('core/email_template')->loadByCode($templateId);

if(!$emailTemplate->getId()){
    $sql = "INSERT INTO {$this->getTable('core_email_template')} (template_code, template_text, template_type, template_subject, template_sender_name, template_sender_email, added_at, modified_at) VALUES
    ('$templateId', '{{template config_path=\"design/email/header\"}}
{{inlinecss file=\"email-inline.css\"}}<body style=\"background:#FFFFFF; font-family:Verdana, Arial, Helvetica, sans-serif; font-size:12px; margin:0; padding:0;\">
<div style=\"background:#FFFFFF; font-family:Verdana, Arial, Helvetica, sans-serif; font-size:12px; margin:0; padding:0;\">

<p>Whoops, you overpaid {{var cryptopaid}} {{var token}} ( about {{var fiat_paid}} {{var fiat_currency}}) </p>

<p>Don\'t worry, here is what to do next:</p>

<p>To get your overpayment refunded, please contact the merchant directly and share your Order ID {{htmlescape var=\$order.getIncrementId()}} and {{var token}} Address to send your refund to.</p>


<p>Tips for Paying with Crypto:</p>

<p>Tip 1) When paying, ensure you send the correct amount in {{var token}}.</p>
<p>Do not manually enter the {{var fiat_currency}} Value.</p>

<p>Tip 2)  If you are sending from an exchange, be sure to correctly factor in their withdrawal fees.</p>

<p>Tip 3) Be sure to successfully send your payment before the countdown timer expires.
This timer is setup to lock in a fixed rate for your payment. Once it expires, rates may change.</p>
</div>
</body>{{template config_path=\"design/email/footer\"}}', 2, '{{var store.getFrontendName()}}: Order {{htmlescape var=\$order.getIncrementId()}}: Important Note', NULL, NULL, NOW(), NOW());";

    $installer->run($sql);
}

$installer->endSetup();