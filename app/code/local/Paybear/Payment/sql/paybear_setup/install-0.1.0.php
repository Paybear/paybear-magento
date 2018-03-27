<?php
/** @var Paybear_Payment_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

try {
    $table = $installer->getConnection()->newTable($installer->getTable('paybear/payment'))
        ->addColumn('paybear_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
            'identity' => true,
        ), 'Paybear Payment ID')
        ->addColumn('order_increment_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, '20', array())
        ->addColumn('token', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array())
        ->addColumn('address', Varien_Db_Ddl_Table::TYPE_TEXT, array())
        ->addColumn('invoice', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array())
        ->addColumn('amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, '20,8', array())
        ->addColumn('confirmations', Varien_Db_Ddl_Table::TYPE_TEXT, null, array())
        ->addColumn('max_confirmations', Varien_Db_Ddl_Table::TYPE_TINYINT, null, array())
        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array())
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array())
        ->addColumn('paid_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array())
        ->addIndex(strtoupper(uniqid('FK_')), 'order_increment_id')
        ->addIndex(strtoupper(uniqid('FK_')), 'token')
    ;

    $installer->getConnection()->createTable($table);

    $installer->getConnection()->insert($installer->getTable('core/config_data'), [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => 'payment/paybear/exchange_locktime',
        'value' => 15
    ]);

    $installer->getConnection()->insert($installer->getTable('core/config_data'), [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => 'payment/paybear/title',
        'value' => 'Crypto Payments (BTC/ETH/LTC and others)'
    ]);

    $installer->getConnection()->insert($installer->getTable('core/config_data'), [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => 'payment/paybear/mispaid_status',
        'value' => 'mispaid'
    ]);

    $installer->getConnection()->insert($installer->getTable('core/config_data'), [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => 'payment/paybear/late_payment_status',
        'value' => 'late_payment'
    ]);

    $installer->getConnection()->insert($installer->getTable('core/config_data'), [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => 'payment/paybear/order_status',
        'value' => 'pending'
    ]);

    $installer->getConnection()->insert($installer->getTable('core/config_data'), [
        'scope' => 'default',
        'scope_id' => 0,
        'path' => 'payment/paybear/awaiting_confirmations_status',
        'value' => 'awaiting_confirmations'
    ]);

    $installer->getConnection()->insert($installer->getTable('sales/order_status'), [
        'status' => 'mispaid',
        'label' => 'Mispaid'
    ]);

    $installer->getConnection()->insert($installer->getTable('sales/order_status'), [
        'status' => 'late_payment',
        'label' => 'Late Payment'
    ]);

    $installer->getConnection()->insert($installer->getTable('sales/order_status'), [
        'status' => 'awaiting_confirmations',
        'label' => 'Awaiting Confirmations'
    ]);

    $statusModel = Mage::getModel('sales/order_status');
    $statusModel->load('mispaid', 'status');
    $statusModel->assignState(Mage_Sales_Model_Order::STATE_CANCELED);

    $statusModel->load('late_payment', 'status');
    $statusModel->assignState(Mage_Sales_Model_Order::STATE_CANCELED);

    $statusModel->load('awaiting_confirmations', 'status');
    $statusModel->assignState(Mage_Sales_Model_Order::STATE_HOLDED);

} catch (Exception $e) {
    var_dump($e);
    die('omg');
    // nothing
}


$installer->endSetup();

