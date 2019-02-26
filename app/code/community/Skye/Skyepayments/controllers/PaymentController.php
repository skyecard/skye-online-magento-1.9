<?php

class Skye_Skyepayments_PaymentController extends Mage_Core_Controller_Front_Action
{
    const LOG_FILE = 'skye.log';
    const SKYE_AU_CURRENCY_CODE = 'AUD';
    const SKYE_AU_COUNTRY_CODE = 'AU';

    /**
     * GET: /skyepayments/payment/start
     *
     * Begin processing payment via skye
     */
    public function startAction()
    {
        if($this->validateQuote()) {
            try {

                $order = $this->getLastRealOrder();
                $payload = $this->buildBeginIplTransactionFields($order);
                $soapUrl  = Skye_Skyepayments_Helper_Data::getSkyeSoapUrl();
                $transactionId  = $this->beginIplTransaction($soapUrl, $payload);

                if ($transactionId != '')
                {
                    $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'Skye authorisation underway.');
                    $order->setStatus(Skye_Skyepayments_Helper_OrderStatus::STATUS_PENDING_PAYMENT);
                    $order->save();
                    $merchantId = Skye_Skyepayments_Helper_Data::getMerchantNumber();
                    $this->postToCheckout(Skye_Skyepayments_Helper_Data::getSkyeOnlineUrl(), $merchantId, $transactionId);
                } else {            
                    $this->restoreCart($order, 'Skye transaction error.');
                    $this->_redirect('checkout/cart');                  
                    $this ->cancelOrder($order);                    
                    Mage::getResourceSingleton('sales/order')->delete($order);                                                         
                }
            } catch (Exception $ex) {
                Mage::logException($ex);
                Mage::log('An exception was encountered in skyepayments/paymentcontroller: ' . $ex->getMessage(), Zend_Log::ERR, self::LOG_FILE);
                Mage::log($ex->getTraceAsString(), Zend_Log::ERR, self::LOG_FILE);
                $this->getCheckoutSession()->addError($this->__('Unable to start Skye Checkout.'));
            }
        } else {
            $this->restoreCart($this->getLastRealOrder(), 'Not a valid quote');
            $this->_redirect('checkout/cart');  
            // cancel order (restore stock) and delete order
            $order = $this->getLastRealOrder();
            $this -> cancelOrder($order);
            Mage::getResourceSingleton('sales/order')->delete($order);
        }
    }

    /**
     * GET: /skyepayments/payment/cancel
     * Cancel an order given an order id
     */
    public function cancelAction()
    {
        $orderId = $this->getRequest()->get('orderId');
        $order =  $this->getOrderById($orderId);
        $transactionId = $this->getRequest()->get('transaction');

        if ($order && $order->getId()) {           
            Mage::log(
                'Requested order cancellation by customer. OrderId: ' . $order->getIncrementId(),
                Zend_Log::DEBUG,
                self::LOG_FILE
            );            
            $this->cancelOrder($order);
            $this->restoreCart($order, 'Requested order cancellation by customer.');
            $order->save();
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * GET: skyepayments/payment/decline
     * Order declined.
     *
     */
    public function declineAction() {
        $orderId = $this->getRequest()->get('orderId');
        $order =  $this->getOrderById($orderId);
        $transactionId = $this->getRequest()->get('transaction');

        if ($order && $order->getId()) {           
            Mage::log(
                'Requested order declined. OrderId: ' . $order->getIncrementId(),
                Zend_Log::DEBUG,
                self::LOG_FILE
            );            
            $this->declineOrder($order);
            $this->restoreCart($order, 'Requested order declined');
            $order->save();
        }
        $this->_redirect('checkout/cart');
    }

    /**
     * GET: skyepayments/payment/refer
     *
     * 
     */
    public function referAction() {
        $orderId = $this->getRequest()->get('orderId');
        $order =  $this->getOrderById($orderId);
        $transactionId = $this->getRequest()->get('transaction');

        if ($order && $order->getId()) {           
            Mage::log(
                'Requested order Referred. OrderId: ' . $order->getIncrementId(),
                Zend_Log::DEBUG,
                self::LOG_FILE
            );            
            $this->referOrder($order);
            $this->restoreCart($order, 'Requested order Referred');
            $order->save();
        }
        $this->_redirect('checkout/cart');
    }
    /**
     * GET: skyepayments/payment/complete
     *
     * callback - skye calls this once the payment process has been completed.
     */
    public function completeAction() {

        $orderId = $this->getRequest()->get("orderId");
        $transactionId = $this->getRequest()->get("transaction");
        $soapUrl  = Skye_Skyepayments_Helper_Data::getSkyeSoapUrl();
        $merchantId = Skye_Skyepayments_Helper_Data::getMerchantNumber();            
        
        if(!$orderId) {
            Mage::log("Skye returned a null order id. This may indicate an issue with the Skye payment gateway.", Zend_Log::ERR, self::LOG_FILE);
            $this->_redirect('checkout/onepage/error', array('_secure'=> false));
            return;
        }

        $order = $this->getOrderById($orderId);

        if(!$order) {
            Mage::log("Skye returned an id for an order that could not be retrieved: $orderId", Zend_Log::ERR, self::LOG_FILE);
            $this->_redirect('checkout/onepage/error', array('_secure'=> false));
            return;
        }

        // ensure that we have a Mage_Sales_Model_Order
        if (get_class($order) !== 'Mage_Sales_Model_Order') {
            Mage::log("The instance of order returned is an unexpected type.", Zend_Log::ERR, self::LOG_FILE);
        }

        $getIplTransaction = array (
                'TransactionID' => str_replace(PHP_EOL, ' ',$transactionId),
                'MerchantId' => str_replace(PHP_EOL, ' ',$merchantId)
            );            
        $applicationStatus = $this->getIplTransaction($soapUrl, $getIplTransaction);
        
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('sales/order');

        if ($applicationStatus == 'ACCEPTED')
        {
            $commitedTransaction = $this->commitIPLTransaction($soapUrl, $getIplTransaction);
        }else{
            $commitedTransaction = false;   
        };

        try {
            $write->beginTransaction();

            $select = $write->select()
                            ->forUpdate()
                            ->from(array('t' => $table),
                                   array('state'))
                            ->where('increment_id = ?', $orderId);

            $state = $write->fetchOne($select);
            if ($state === Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                Mage::log("Pending payment", Zend_Log::ALERT, self::LOG_FILE);
                $whereQuery = array('increment_id = ?' => $orderId);

                if ($commitedTransaction)
                    $dataQuery = array('state' => Mage_Sales_Model_Order::STATE_PROCESSING);
                else
                    $dataQuery = array('state' => Mage_Sales_Model_Order::STATE_CANCELED);

                $write->update($table, $dataQuery, $whereQuery);
            } else {
                Mage::log("Not Pending payment ".$getIplTransaction, Zend_Log::ALERT, self::LOG_FILE);
                $write->commit();

                if ($commitedTransaction)
                    $this->_redirect('checkout/onepage/success', array('_secure'=> false));
                else
                    $this->_redirect('checkout/onepage/failure', array('_secure'=> false));

                return;
            }

            $write->commit();
        } catch (Exception $e) {
            $write->rollback();
            Mage::log("Transaction failed. Order status not updated", Zend_Log::ERR, self::LOG_FILE);
            $this->_redirect('checkout/onepage/error', array('_secure'=> false));
            return;
        }

        $order = $this->getOrderById($orderId);

        if ($commitedTransaction) {
            Mage::log("Committed transaction ".$getIplTransaction, Zend_Log::ALERT, self::LOG_FILE);
            $orderState = Skye_Skyepayments_Helper_OrderStatus::STATUS_PROCESSING;
            $orderStatus = Mage::getStoreConfig('payment/skyepayments/skyepay_approved_order_status');
            $emailCustomer = Mage::getStoreConfig('payment/skyepayments/email_customer');
            if (!$this->statusExists($orderStatus)) {
                $orderStatus = $order->getConfig()->getStateDefaultStatus($orderState);
            }

            $order->setState($orderState, $orderStatus ? $orderStatus : true, $this->__("Skye authorisation success. Transaction #$transactionId"), $emailCustomer);
            $order->save();

            if ($emailCustomer) {
                $order->sendNewOrderEmail();
            }

            $invoiceAutomatically = Mage::getStoreConfig('payment/skyepayments/automatic_invoice');
            if ($invoiceAutomatically) {
                $this->invoiceOrder($order);
            }
        } else {
            Mage::log("Not committed transaction ".$getIplTransaction, Zend_Log::ALERT, self::LOG_FILE);
            $order->addStatusHistoryComment($this->__("Order #".($order->getId())." was declined by skye. Transaction #$transactionId."));
            $order
                ->cancel()
                ->setStatus(Skye_Skyepayments_Helper_OrderStatus::STATUS_DECLINED)
                ->addStatusHistoryComment($this->__("Order #".($order->getId())." was canceled by customer."));
            
            $order->save();
            $this->restoreCart($order, 'Not committed transaction.');
        }
        Mage::getSingleton('checkout/session')->unsQuoteId();
        
        if($commitedTransaction){
            $this->_redirect('checkout/onepage/success', array('_secure'=> false));
        }else{
            $this->_redirect('checkout/onepage/failure', array('_secure'=> false));
        }
        return;
    }

     private function getIplTransaction($checkoutUrl, $params)
    {     
        $soapclient = new SoapClient($checkoutUrl, ['trace' => true, 'exceptions' => true]);   
        try{                   
            $response = $soapclient->__soapCall('GetIPLTransactionStatus',[$params]);
            $iplTransactionResult = $response->GetIPLTransactionStatusResult;         
        }catch(Exception $ex){            
           Mage::log("An exception was encountered in getIPLTransaction: ".($e->getMessage()), Zend_Log::ERR, self::LOG_FILE);
            Mage::log("An exception was encountered in getIPLTransaction: ".($soapclient->__getLastRequest()), Zend_Log::ERR, self::LOG_FILE);    
        }
        Mage::log("Finish getIplTransaction!!!", Zend_Log::ALERT, self::LOG_FILE); 
        return $iplTransactionResult;
    }

    private function commitIPLTransaction($checkoutUrl, $params)
    {        
        $commitIplTransactionResult = false;
        $soapclient = new SoapClient($checkoutUrl, ['trace' => true, 'exceptions' => true]);
        try{                                   
            $response = $soapclient->__soapCall('CommitIPLTransaction',[$params]);
            $commitIplTransactionResult = $response->CommitIPLTransactionResult;
            Mage::log("Response->".($soapclient->__getLastResponse()), Zend_Log::ALERT, self::LOG_FILE);       
            Mage::log("Request->".($soapclient->__getLastRequest()), Zend_Log::ALERT, self::LOG_FILE); 
            
        }catch(Exception $ex){            
            Mage::log("An exception was encountered in commitIPLTransaction: ".($e->getMessage()), Zend_Log::ERR, self::LOG_FILE);
            Mage::log("An exception was encountered in commitIPLTransaction: ".($soapclient->__getLastRequest()), Zend_Log::ERR, self::LOG_FILE);                    
        }     
                       
        return $commitIplTransactionResult;
    }

    private function statusExists($orderStatus) {
        try {
            $orderStatusModel = Mage::getModel('sales/order_status');
            if ($orderStatusModel) {
                $statusesResCol = $orderStatusModel->getResourceCollection();
                if ($statusesResCol) {
                    $statuses = $statusesResCol->getData();
                    foreach ($statuses as $status) {
                        if ($orderStatus === $status["status"]) return true;
                    }
                }
            }
        } catch(Exception $e) {
            Mage::log("Exception searching statuses: ".($e->getMessage()), Zend_Log::ERR, self::LOG_FILE);
        }
        return false;
    }

    private function sendResponse($isFromAsyncCallback, $result, $orderId){
        if($isFromAsyncCallback){
            // if from POST request (from asynccallback)
            $jsonData = json_encode(["result"=>$result, "order_id"=> $orderId]);
            $this->getResponse()->setHeader('Content-type', 'application/json');
            $this->getResponse()->setBody($jsonData);
        } else {
            // if from GET request (from browser redirect)
            if($result=="completed"){
                $this->_redirect('checkout/onepage/success', array('_secure'=> false));
            }else{
                $this->_redirect('checkout/onepage/failure', array('_secure'=> false));
            }

        }
        return;
    }

    private function invoiceOrder(Mage_Sales_Model_Order $order) {

        if(!$order->canInvoice()){
            Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
        }

        $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

        if (!$invoice->getTotalQty()) {
            Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
        }

        $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
        $invoice->register();
        $transactionSave = Mage::getModel('core/resource_transaction')
        ->addObject($invoice)
        ->addObject($invoice->getOrder());

        $transactionSave->save();
    }

    /**
     * Constructs a SOAP request to Skye IFOL
     * @param $order
     * @return array
     */
    private function buildBeginIplTransactionFields($order){
        Mage::log('buildBeginIplTransactionFields', Zend_Log::ALERT, self::LOG_FILE);
        if($order == null)
        {
            Mage::log('Unable to get order from last lodged order id. Possibly related to a failed database call.', Zend_Log::ALERT, self::LOG_FILE);
            $this->_redirect('checkout/onepage/error', array('_secure'=> false));
        }

        $shippingAddress = $order->getShippingAddress();
        $shippingAddressParts = explode(PHP_EOL, $shippingAddress->getData('street'));
        $shippingAddressGroup = $this->formatAddress($shippingAddressParts, $shippingAddress);

        $billingAddress = $order->getBillingAddress();
        $billingAddressParts = explode(PHP_EOL, $billingAddress->getData('street'));
        $billingAddressGroup = $this->formatAddress($billingAddressParts, $billingAddress);       

        $orderId = (int)$order->getRealOrderId();            
        $orderTotalAmt = str_replace(PHP_EOL, ' ', $order->getTotalDue());
        if ($order->getPayment()->getAdditionalData() != null)
        {
            $skyeTermCode = $order->getPayment()->getAdditionalData();
        } else {
            $skyeTermCode = Skye_Skyepayments_Helper_Data::getDefaultProductOffer();
        }
        $transactionInformation = array(            
            'MerchantId' => str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getMerchantNumber()),          
            'OperatorId' => str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getOperatorId()),
            'Password' => str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getOperatorPassword()),
            'EncPassword' => '',            
            'Offer' => str_replace(PHP_EOL, ' ',$skyeTermCode),
            'CreditProduct'=> str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getCreditProduct()),
            'NoApps' => '',
            'OrderNumber' => str_replace(PHP_EOL, ' ', $orderId),            
            'ApplicationId' => '',
            'Description' => '',
            'Amount' => str_replace(PHP_EOL, ' ', $order->getTotalDue()),            
            'ExistingCustomer' => '0',
            'Title' => '', 
            'FirstName' => str_replace(PHP_EOL, ' ', $order->getCustomerFirstname()),             
            'MiddleName' => '', 
            'Surname' => str_replace(PHP_EOL, ' ', $order->getCustomerLastname()),            
            'Gender' => '', 
            'BillingAddress' => $billingAddressGroup, 
            'DeliveryAddress' => $shippingAddressGroup,
            'WorkPhoneArea' => '',
            'WorkPhoneNumber' => '',
            'HomePhoneArea' => '',
            'HomePhoneNumber' => '',            
            'MobilePhoneNumber' => preg_replace('/\D+/', '', $billingAddress->getData('telephone')),            
            'EmailAddress' => str_replace(PHP_EOL, ' ', $order->getData('customer_email')),
            'Status' => '',
            'ReturnApprovedUrl' => str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getCompleteUrl($orderId)),
            'ReturnDeclineUrl' => str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getDeclinedUrl($orderId)),
            'ReturnWithdrawUrl' => str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getCancelledUrl($orderId)),
            'ReturnReferUrl' => str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getReferUrl($orderId)),
            'SuccessPurch' => '',
            'SuccessAmt' => '',
            'DateLastPurch' => '',
            'PayLastPurch' => '',
            'DateFirstPurch' => '',
            'AcctOpen' => '',
            'CCDets' => '',
            'CCDeclines' => '',
            'CCDeclineNum' => '',
            'DeliveryAddressVal' => '',
            'Fraud' => '',
            'EmailVal' => '',
            'MobileVal' => '',
            'PhoneVal' => '',
            'TransType' => '',
            'UserField1' => '',
            'UserField2' => '',
            'UserField3' => '',
            'UserField4' => '',
            'SMSCustLink' => '',
            'EmailCustLink' => '',
            'SMSCustTemplate' => '',
            'EmailCustTemplate' => '',
            'SMSCustTemplate' => '',
            'EmailDealerTemplate' => '',
            'EmailDealerSubject' => '',
            'EmailCustSubject' => '',
            'DealerEmail' => '',
            'DealerSMS' => '',
            'CreditLimit' => ''
        );

        $params = array(                
            'TransactionInformation' => $transactionInformation, 
            'SecretKey' => Skye_Skyepayments_Helper_Data::getApiKey()     
        );        

        return $params;

    }


    private function formatAddress($addressParts, $address)
    {        

        $addressStreet0 = explode(' ', $addressParts[0]);  
        $addressStreetCount = count($addressStreet0);

            foreach ($addressStreet0 as $addressValue0) {
                
                if (is_numeric($addressValue0)) 
                { 
                    $addressNoStr = $addressValue0;
                }                
            }

            if ($addressStreetCount == 3)
            {                
             $addressNameStr = $addressStreet0[$addressStreetCount - 2];                                       
             $addressTypeStr = $addressStreet0[$addressStreetCount -1];

            }                         
        
        if (count($addressParts) > 1)
        {
            $addressStreet1 = explode(' ', $addressParts[1]);            
            $addressStreetCount = count($addressStreet1);

            foreach ($addressStreet1 as $addressValue1) {
                
                if (is_numeric($addressValue1)) 
                { 
                    $addressNoStr = $addressValue1;
                } else {
                    $addressNameStr = $addressValue1;
                }
            } 
            if ($addressStreetCount == 1)
            {
                $addressTypeStr = $addressStreet1[0];
            } else {
                $addressTypeStr = $addressStreet1[$addressStreetCount -1]; 
            }

        }
        $addressTypeStrFmt = strtolower($addressTypeStr);

        $formattedAddress = array(           
            'AddressType' => 'Residential', 
            'UnitNumber' => '', 
            'StreetNumber' => $addressNoStr,
            'StreetName' => $addressNameStr,
            'StreetType' => ucfirst($addressTypeStrFmt),
            'Suburb' => str_replace(PHP_EOL, ' ', $address->getData('city')), 
            'City' => str_replace(PHP_EOL, ' ', $address->getData('city')),
            'State' => str_replace(PHP_EOL, ' ', $address->getData('region')),             
            'Postcode' => str_replace(PHP_EOL, ' ', $address->getData('postcode')), 
            'DPID' => ''
        );   
        
        return $formattedAddress;
    }
    /**
     * Calls the SOAP service
     * @param $checkoutUrl
     * @param $params
     * @return $transactionId
     */
    private function beginIplTransaction($checkoutUrl, $params)
    {
        $transactionId = '';        
        $soapclient = new SoapClient($checkoutUrl, ['trace' => true, 'exceptions' => true]);
        try{        
            $response = $soapclient->__soapCall('BeginIPLTransaction',[$params]);             
            $transactionId = $response->BeginIPLTransactionResult;                                
        }catch(Exception $ex){            
            Mage::log("Exception: response->" .$transactionId . $ex->getMessage(), Zend_Log::ERR, self::LOG_FILE);
            preg_match('/Validation(.+)|Error(.+)/', $ex->getMessage() , $arrMatches);            
            Mage::log("Exception: request->" . $soapclient->__getLastRequest(), Zend_Log::ERR, self::LOG_FILE);
            Mage::log("Exception: response->" . $soapclient->__getLastResponse(), Zend_Log::ERR, self::LOG_FILE);
            //$this->_redirect('checkout/onepage/error', array('_secure'=> false));            
            $this->getCheckoutSession()->addError($this->__('Unable to start Skye Checkout: '.$soapclient->__getLastResponse()));
            //return;            
        }    
        return $transactionId;
    }


    /**
     * checks the quote for validity
     * @throws Mage_Api_Exception
     */
    private function validateQuote()
    {
        $specificCurrency = null;

        if ($this->getSpecificCountry() == self::SKYE_AU_COUNTRY_CODE) {
            $specificCurrency = self::SKYE_AU_CURRENCY_CODE;
        }

        $order = $this->getLastRealOrder();
        $minOrderAmount = str_replace(PHP_EOL, ' ', Skye_Skyepayments_Helper_Data::getMerchantNumber());
        if($order->getTotalDue() < (int)$minOrderAmount) {
            Mage::getSingleton('checkout/session')->addError("Skye doesn't support purchases less than $".$minOrderAmount.".");
            return false;
        }

        if($order->getBillingAddress()->getCountry() != $this->getSpecificCountry() || $order->getOrderCurrencyCode() != $specificCurrency ) {
            Mage::getSingleton('checkout/session')->addError("Orders from this country are not supported by Skye. Please select a different payment option.");
            return false;
        }

        if( !$order->isVirtual && $order->getShippingAddress()->getCountry() != $this->getSpecificCountry()) {
            Mage::getSingleton('checkout/session')->addError("Orders shipped to this country are not supported by Skye. Please select a different payment option.");
            return false;
        }

        return true;
    }

    /**
     * Get current checkout session
     * @return Mage_Core_Model_Abstract
     */
    private function getCheckoutSession() {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Injects a self posting form to the page in order to kickoff skye checkout process
     * @param $checkoutUrl
     * @param $payload
     */
    private function postToCheckout($checkoutUrl, $merchantId, $transactionId)
    {
        echo
        "<html>
            <body>            
            <form id='form' action='$checkoutUrl' method='get'>";        
            echo "<input type='hidden' id='seller' name='seller' value='$merchantId'/>";
            echo "<input type='hidden' id='ifol' name='ifol' value='true'/>";
            echo "<input type='hidden' id='transactionId' name='transactionId' value='$transactionId'/>";
        echo
        '</form>
            </body>';
        echo
        '<script>
                var form = document.getElementById("form");
                form.submit();
            </script>
        </html>';
    }

    /**
     * returns an Order object based on magento's internal order id
     * @param $orderId
     * @return Mage_Sales_Model_Order
     */
    private function getOrderById($orderId)
    {
        return Mage::getModel('sales/order')->loadByIncrementId($orderId);
    }

    /**
     * retrieve the merchants skye api key
     * @return mixed
     */
    private function getApiKey()
    {
        return Mage::getStoreConfig('payment/skyepayments/api_key');
    }

    /**
    * Get specific country
    *
    * @return string
    */
    public function getSpecificCountry()
    {
      return Mage::getStoreConfig('payment/skyepayments/specificcountry');
    }

    /**
     * retrieve the last order created by this session
     * @return null
     */
    private function getLastRealOrder()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();

        $order =
            ($orderId)
                ? $this->getOrderById($orderId)
                : null;
        return $order;
    }

    /**
     * Method is called when an order is cancelled by a customer. As an Oxipay reference is only passed back to
     * Magento upon a success or decline outcome, the method will return a message with a Magento reference only.
     *
     * @param Mage_Sales_Model_Order $order
     * @return $this
     * @throws Exception
     */
    private function cancelOrder(Mage_Sales_Model_Order $order)
    {
        if (!$order->isCanceled()) {
            $order
                ->cancel()
                ->addStatusHistoryComment($this->__("Order #".($order->getId())." was canceled by customer."));
        }
        return $this;
    }

     /**
     * Method is called when an order is declined by Skye. As an Oxipay reference is only passed back to
     * Magento upon a success or decline outcome, the method will return a message with a Magento reference only.
     *
     * @param Mage_Sales_Model_Order $order
     * @return $this
     * @throws Exception
     */
    private function declineOrder(Mage_Sales_Model_Order $order)
    {
        if (!$order->isCanceled()) {
            $order
                ->cancel()
                ->addStatusHistoryComment($this->__("Order #".($order->getId())." was declined by Skye."));
        }
        return $this;
    }

    /**
     * Method is called when an order is declined by Skye. As an Oxipay reference is only passed back to
     * Magento upon a success or decline outcome, the method will return a message with a Magento reference only.
     *
     * @param Mage_Sales_Model_Order $order
     * @return $this
     * @throws Exception
     */
    private function referOrder(Mage_Sales_Model_Order $order)
     {
        if (!$order->isCanceled()) {
            $order
                ->cancel()
                ->addStatusHistoryComment($this->__("Order #".($order->getId())." is in refer status on Skye."));
        }
        return $this;
    }
    /**
     * Loads the cart with items from the order
     * @param Mage_Sales_Model_Order $order
     * @param $message
     * @return $this
     */
    private function restoreCart(Mage_Sales_Model_Order $order, $message = '', $refillStock = false)
    {
        // return all products to shopping cart
        $quoteId = $order->getQuoteId();
        $quote   = Mage::getModel('sales/quote')->load($quoteId);

        if ($quote->getId()) {
            $quote->setIsActive(1);
            if ($refillStock) {
                $items = $this->_getProductsQty($quote->getAllItems());
                if ($items != null ) {
                    Mage::getSingleton('cataloginventory/stock')->revertProductsSale($items);
                }
            }

            $quote->setReservedOrderId(null);
            $quote->save();
            $this->getCheckoutSession()->replaceQuote($quote);
            Mage::getSingleton('checkout/session')->addNotice($message);
        }
        return $this;
    }

    /**
     * Prepare array with information about used product qty and product stock item
     * result is:
     * array(
     *  $productId  => array(
     *      'qty'   => $qty,
     *      'item'  => $stockItems|null
     *  )
     * )
     * @param array $relatedItems
     * @return array
     */
    protected function _getProductsQty($relatedItems)
    {
        $items = array();
        foreach ($relatedItems as $item) {
            $productId  = $item->getProductId();
            if (!$productId) {
                continue;
            }
            $children = $item->getChildrenItems();
            if ($children) {
                foreach ($children as $childItem) {
                    $this->_addItemToQtyArray($childItem, $items);
                }
            } else {
                $this->_addItemToQtyArray($item, $items);
            }
        }
        return $items;
    }


    /**
     * Adds stock item qty to $items (creates new entry or increments existing one)
     * $items is array with following structure:
     * array(
     *  $productId  => array(
     *      'qty'   => $qty,
     *      'item'  => $stockItems|null
     *  )
     * )
     *
     * @param Mage_Sales_Model_Quote_Item $quoteItem
     * @param array &$items
     */
    protected function _addItemToQtyArray($quoteItem, &$items)
    {
        $productId = $quoteItem->getProductId();
        if (!$productId)
            return;
        if (isset($items[$productId])) {
            $items[$productId]['qty'] += $quoteItem->getTotalQty();
        } else {
            $stockItem = null;
            if ($quoteItem->getProduct()) {
                $stockItem = $quoteItem->getProduct()->getStockItem();
            }
            $items[$productId] = array(
                'item' => $stockItem,
                'qty'  => $quoteItem->getTotalQty()
            );
        }
    }
}
