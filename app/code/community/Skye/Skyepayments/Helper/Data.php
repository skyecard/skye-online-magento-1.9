<?php

/**
 * Class Skye_Skyepayments_Helper_Data
 *
 * Provides helper methods for retrieving data for the Skye plugin
 */
class Skye_Skyepayments_Helper_Data extends Mage_Core_Helper_Abstract
{   
    public static function init()
    {
    }

    /**
     * get the merchant number
     * @return string
     */
    public static function getMerchantNumber() {
        return Mage::getStoreConfig('payment/skyepayments/merchant_number');
    }

    /**
     * get the operator id
     * @return string
     */
    public static function getOperatorId() {
        return Mage::getStoreConfig('payment/skyepayments/operator_id');
    }

    /**
     * get the operator password
     * @return string
     */
    public static function getOperatorPassword() {
        return Mage::getStoreConfig('payment/skyepayments/operator_password');
    }

    /**
     * Check if customer should only get default offer or option to select
     * @return boolean
     */
    public static function getCustomerProductOption() {
        return Mage::getStoreConfig('payment/skyepayments/default_product_only');
    } 
    /**
     * get the default product offer
     * @return string
     */
    public static function getDefaultProductOffer() {
        return Mage::getStoreConfig('payment/skyepayments/default_product_offer');
    }   
    /**
     * get the default product offer description
     * @return string
     */
    public static function getDefaultProductDescription() {
        return Mage::getStoreConfig('payment/skyepayments/default_product_description');
    }       
    /**
     * get the credit product
     * @return string
     */
    public static function getCreditProduct() {
        return Mage::getStoreConfig('payment/skyepayments/credit_product');
    }

     /**
     * get the api key
     * @return string
     */
    public static function getApiKey() {
        return Mage::getStoreConfig('payment/skyepayments/api_key');
    }

    /**
     * get the URL of the configured skye SOAP service URL
     * @return string
     */
    public static function getSkyeSoapUrl() {
        return Mage::getStoreConfig('payment/skyepayments/skyesoap_url');
    }

    /**
     * get the URL of the configured skye online url
     * @return string
     */
    public static function getSkyeOnlineUrl() {
        return Mage::getStoreConfig('payment/skyepayments/skyeonline_url');
    }

    /**
    * get min order
    * @return string
    */
    public static function getSkyeMinimum() {
        return Mage::getStoreConfig('payment/skyepayments/min_order_total');
    }
    /**
     * @return string
     */
    public static function getCompleteUrl($orderId) {
        return Mage::getBaseUrl() . 'skyepayments/payment/complete?orderId='.$orderId.'&transaction=[TRANSACTIONID]';
    }

    /**
     * @return string
     */
    public static function getCancelledUrl($orderId) {
        return Mage::getBaseUrl() . 'skyepayments/payment/cancel?orderId='.$orderId.'&transaction=[TRANSACTIONID]';
    }

    /**
     * @return string
     */
    public static function getDeclinedUrl($orderId) {
        return Mage::getBaseUrl() . 'skyepayments/payment/decline?orderId='.$orderId.'&transaction=[TRANSACTIONID]';
    }

    /**
     * @return string
     */
    public static function getReferUrl($orderId) {
        return Mage::getBaseUrl() . 'skyepayments/payment/refer?orderId='.$orderId.'&transaction=[TRANSACTIONID]';
    }
}
Skye_Skyepayments_Helper_Data::init();