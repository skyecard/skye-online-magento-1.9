<?php
/**
 * Created by PhpStorm.
 * User: jimbur
 * Date: 22/09/2016
 * Time: 3:46 PM
 */
$installer = $this;

$installer->startSetup();

    // add default Oxipay Status "Oxipay Processed" for STATE_PROCESSING state    
$installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('pending_skye', 'Pending Skye');");

// update the existing status
$installer->run("UPDATE `{$this->getTable('sales_order_status')}` set `label`= 'Skye Processed' where `status`='skye_processed'");


// @todo Skye Cancelled
$installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('cancelled_skye', 'Cancelled Skye');");

// Declined Skye
$installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('declined_skye', 'Declined Skye');");


$installer->endSetup();