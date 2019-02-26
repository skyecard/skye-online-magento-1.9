<?php
/**
 * Created by PhpStorm.
 * User: jimbur
 * Date: 22/09/2016
 * Time: 3:46 PM
 */
$installer = $this;

$installer->startSetup();

    // add default Skye Status "Skye Processed" for STATE_PROCESSING state
    $processingState  = Mage_Sales_Model_Order::STATE_PROCESSING;
    $cancelledState = Mage_Sales_Model_Order::STATE_CANCELED;
    $skyeProcessingStatus = 'skye_processed';
    $skyeCancelStatus = 'cancelled_skye';
    $installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('{$skyeProcessingStatus}', 'Skye Processed');");
    $installer->run("INSERT INTO `{$this->getTable('sales_order_status_state')}` (`status`, `state`, `is_default`) VALUES ('{$skyeProcessingStatus}', '{$processingState}', '0');");
    $installer->run("INSERT INTO `{$this->getTable('sales_order_status')}` (`status`, `label`) VALUES ('{$skyeCancelStatus}', 'Skye Cancelled');");
    $installer->run("INSERT INTO `{$this->getTable('sales_order_status_state')}` (`status`, `state`, `is_default`) VALUES ('{$skyeCancelStatus}', '{$cancelledState}', '0');");

$installer->endSetup();