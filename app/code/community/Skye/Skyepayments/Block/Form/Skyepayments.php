<?php

/**
 * Class Skye_Skyepayments_Block_Form_Skyepayments
 * @Description Code behind for the custom Skye payment form.
 * @Remarks Invokes the view template in a distant folder structure: ~/app/design/frontend/base/default/template/skyepayments/form.phtml
 *
 */
class Skye_Skyepayments_Block_Form_Skyepayments extends Mage_Payment_Block_Form
{
    const LOG_FILE = 'skye.log';

    protected function _construct()
    {    	
        $mark = Mage::getConfig()->getBlockClassName('core/template');
        $mark = new $mark;
        $mark->setTemplate('skyepayments/mark.phtml');
        $this->setMethodLabelAfterHtml($mark->toHtml());
        parent::_construct();
        $this->setTemplate('skyepayments/form.phtml');           
    }

    public function getDefaultProductOffer()
    {
        $defaultOffer = Skye_Skyepayments_Helper_Data::getDefaultProductOffer();
        $merchantId = Skye_Skyepayments_Helper_Data::getMerchantNumber();
        $orderTotalAmount = Mage::getModel('checkout/cart')->getQuote()->getGrandTotal();
        $service_url = 'https://um1fnbwix7.execute-api.ap-southeast-2.amazonaws.com/dev/?id='.$merchantId.'&amount='.$orderTotalAmount.'&callback=jsonpCallback';          
        $data = file_get_contents($service_url);
         if($data[0] !== '[' && $data[0] !== '{') { // we have JSONP
            $data = substr($data, strpos($data, '('));
        }
        $result = json_decode(trim($data,'();'), true);
        $data = trim($data,'();');
        return $data;
    }       

    public function getCustomerProductOffer()
    {
        $customerProductOffer = Skye_Skyepayments_Helper_Data::getCustomerProductOption();
        return $customerProductOffer;
    }

    public function getDefaultProductDescription()
    {
        $defaultOfferDescription = Skye_Skyepayments_Helper_Data::getDefaultProductDescription();
        return $defaultOfferDescription;
    }

    public function getOrderTotalAmount()
    {
        $grandTotal = Mage::getModel('checkout/cart')->getQuote()->getGrandTotal();
        return $grandTotal;
    }
}