<?php

$installer = $this;

$installer->startSetup();

$installer->run("DELETE FROM `{$installer->getTable('sales_order_status_state')}` WHERE status='skye_processing';");
$installer->run("DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE status='skye_processing';");
$installer->run("DELETE FROM `{$installer->getTable('sales_order_status_state')}` WHERE status='cancelled_skye';");
$installer->run("DELETE FROM `{$installer->getTable('sales_order_status')}` WHERE status='cancelled_skye';");

$installer->endSetup();
?>
