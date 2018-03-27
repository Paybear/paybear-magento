<?php
/** @var Paybear_Payment_Model_Resource_Setup $installer */
$installer = $this;
$installer->startSetup();

try {
    $table = $installer->getConnection()->newTable($installer->getTable('paybear/paymenttxn'))
        ->addColumn('id_txn', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'unsigned' => true,
            'nullable' => false,
            'primary' => true,
            'identity' => true,
        ), 'Paybear Payment Txn ID')
        ->addColumn('txn', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array())
        ->addColumn('address', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array())
        ->addColumn('txn_amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, '20,8', array())
        ->addColumn('order_amount', Varien_Db_Ddl_Table::TYPE_DECIMAL, '20,8', array())
        ->addColumn('invoice', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array())
        ->addColumn('token', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array())
        ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 255, array())
        ->addColumn('confirmations', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array())


        ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array())
        ->addColumn('updated_at', Varien_Db_Ddl_Table::TYPE_DATETIME, null, array())

        ->addIndex(strtoupper(uniqid('FK_')), 'txn');

    $installer->getConnection()->createTable($table);

} catch (Exception $e) {
    Mage::throwException($e);
}


$installer->endSetup();

