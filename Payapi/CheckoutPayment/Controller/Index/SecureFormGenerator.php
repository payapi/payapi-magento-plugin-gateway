<?php
namespace Payapi\CheckoutPayment\Controller\Index;

use Magento\Framework\App\Action\Context;
use \Magento\Catalog\Api\ProductRepositoryInterface;

class SecureFormGenerator extends \Magento\Framework\App\Action\Action {
	protected $_objectManager;
    protected $_logger;
    protected $_store;
    protected $_storeManager;
    protected $_cartManagementInterface;
    protected $_cartRepositoryInterface;
    protected $_productRepository;
    protected $_defaultShippingMethod;

	public function __construct(
	Context $context, 
    \Payapi\CheckoutPayment\Logger\Logger $logger,
	\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
    \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
    \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
    \Magento\Store\Model\StoreManagerInterface $storeManager,
    \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
    ) {
    	$this->resultJsonFactory = $resultJsonFactory;
        $this->_logger = $logger;
        $this->_cartManagementInterface = $cartManagementInterface;
        $this->_cartRepositoryInterface = $cartRepositoryInterface;
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_storeManager = $storeManager;        
        $this->_productRepository = $productRepository;
        $this->_store = $this->_storeManager->getStore();
        
        $paymentHelper =  $this->_objectManager->get('\Magento\Payment\Helper\Data');
        $paymentMethod = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");
        $this->_defaultShippingMethod = $paymentMethod->getConfigData('instantbuy_shipping_method');

        $this->_logger->debug("Execute SecureFormGenerator. Constructor. Default shipping: ".$this->_defaultShippingMethod);
    	parent::__construct($context);
	}
	
	public function execute() {
        $this->_logger->debug("Execute SecureFormGenerator");
    	$result = $this->resultJsonFactory->create();
        if ($this->getRequest()->isAjax()) {            
            $this->_logger->debug("POST buy");   
            $referenceQuoteId = $this->getRequest()->getPostValue('referenceQuoteId');
            $this->_logger->debug("POST buy QuoteId: ".$referenceQuoteId);   
            $shippingExtraProd = $this->getRequest()->getPostValue('shippingProduct');
            $this->_logger->debug("POST buy ExtraShipping:".json_encode($shippingExtraProd));   
            $checkoutAddress = $this->getRequest()->getPostValue('checkoutAddress');
            $this->_logger->debug("POST buy CheckoutAddress: ".json_encode($checkoutAddress));   
            $ipaddress = $this->getRequest()->getPostValue('ipaddress');
            $this->_logger->debug("POST buy IpAddress: ".$ipaddress);   
            
            if(!$referenceQuoteId){  
                $session = $this->_objectManager->get('Magento\Checkout\Model\Session');
                $quote = $session->getQuote();
                $referenceQuoteId = $quote->getId();
                $this->_logger->debug("POST buy session QuoteId: ".$referenceQuoteId);   
            }
            $secureformObject = $this->postSecureForm($referenceQuoteId, $shippingExtraProd, $checkoutAddress, $ipaddress);
                return $result->setData($secureformObject); 
        }
    }

	
    protected function postSecureForm($referenceQuoteId, $shippingExtraProd = false, $checkoutAddress = false, $ipaddress = ""){        
        //Clone quote to make it indepentent of the cart and keeping it static
        $cart_id = $this->_cartManagementInterface->createEmptyCart();
        $referenceQuote = $this->_cartRepositoryInterface->get($referenceQuoteId);
        $newQuote = $this->_cartRepositoryInterface->get($cart_id);
        $newQuote = $newQuote->merge($referenceQuote);
   
        $secureformData = $this->completeQuoteAndGetData($newQuote, $shippingExtraProd, $checkoutAddress, $ipaddress);
        return $secureformData;

    }

    public function getInstantBuySecureForm($productId, $opts = ['qty => 1'], $ipaddress = ""){
        $this->_logger->debug("GET INFO TO GENERATE METAS");                    
        $quote = $this->generateNewQuoteWithProduct($productId, $opts);
        if($quote){
            $secureformData = $this->completeQuoteAndGetData($quote, false,false, $ipaddress);
            return $secureformData;
        }
        return null;
    }


    protected function generateNewQuoteWithProduct($productId, $opts = ['qty' => 1]){
        $this->_logger->debug("Loading product ".$productId);
              
        $product = $this->_productRepository->getById($productId,false,$this->_store->getId());


        if($product){
            $quoteId = $this->_cartManagementInterface->createEmptyCart();
            $quote = $this->_cartRepositoryInterface->get($quoteId);

            $optsObj = new \Magento\Framework\DataObject($opts);
            $quote->addProduct(
                $product,
                $optsObj
            );
        }else{
            $this->_logger->debug("PRODUCT NOT FOUND ".$productId);
            return null;
        }
        return $quote;
    }

	protected function completeQuoteAndGetData($quote, $shippingExtraProd, $checkoutAddress = false, $ipaddress = ""){
		$this->_logger->debug("COMPLETE QUOTE");
        
        //$websiteId = $store->getWebsiteId();
                
        $quote->setStore($this->_store);
        // if you have already buyer id then you can load customer directly
        $quote->setCurrency();
        
        //Set customer if logged
        //Customer login
        $customerSession = $this->_objectManager->get('Magento\Customer\Model\Session');
        $customer = null;

        if($customerSession->isLoggedIn()) {
            $customer =  $customerSession->getCustomer();            
            $quote->assignCustomer($customer); //Assign quote to customer
            $quote->setCustomerEmail($customer->getEmail());
            $userAddress = $om->create('Magento\Customer\Model\Address')->load($customer->getDefaultShipping());
        }else{           
            $quote->setCustomerIsGuest(true);
			$userAddress = null;
        }

        //Shipping Address
        //1. use checkout addr (Always in checkout)
        //2. use user addr if logged in (Instant buy or post without address)
        //3. use ip country
        //4. use store country
        if($checkoutAddress){
            $finalAddr = $checkoutAddress;
        }else if($userAddress){
            $finalAddr = $userAddress;
        }else{
        	$finalAddr = $this->getShippingFromIp($ipaddress);            
        	if(!isset($finalAddr) || $finalAddr == null){
        		$scopeConfig = $this->_objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        		$countryCode = $scopeConfig->getValue('general/store_information/country_id');
        		$region = $scopeConfig->getValue('general/store_information/region');
        		$finalAddr = [
                    'firstname'    => 'xxxxx', //address Details
                    'lastname'     => 'xxxxx',
                    'street' => 'xxxxx',
                    'city' => 'xxxxx',
                    'country_id' => $countryCode,
                    'region' => $region,
                    'postcode' => '*',
                    'telephone' => '0',
                    'fax' => '0',
                    'save_in_address_book' => 0
        	   ];
        	}            
        }
        $this->_logger->debug(json_encode($finalAddr));
        //Set Address to quote @todo add section in order data for seperate billing and handle it
        $quote->getBillingAddress()->addData($finalAddr);
        $quote->getShippingAddress()->addData($finalAddr);

        // Shipping method       
        $shippingMethod = $this->_defaultShippingMethod;
        if($shippingExtraProd){
            $shippingMethod = $shippingExtraProd["id"];
        }

        $shippingRate = $this->_objectManager->get('Magento\Quote\Model\Quote\Address\Rate');
        $shippingRate
            ->setCode($shippingMethod)
            ->getPrice(1);
        $shippingAddress = $quote->getShippingAddress();
        //@todo set in order data

        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($shippingMethod); //shipping method

        $quote->setPaymentMethod('payapi_checkoutpayment_secure_form_post'); //payment method
        $quote->setInventoryProcessed(false);
        $quote->getPayment()->importData(['method' => 'payapi_checkoutpayment_secure_form_post']);
        $quote->collectTotals();
        $quote->save();
        
        return $this->getSecureFormData($quote, $finalAddr, $shippingExtraProd);
	}


    protected function getSecureFormData($quoteTmp, $shippingAddress = [], $shippingExtraProd = false){
        $this->_logger->debug("GENERATE SECURE FORM DATA .");
        $totals = $quoteTmp->getShippingAddress()->getData();

        $baseExclTax = $totals['base_subtotal_with_discount'];
        $taxAmount = $totals['tax_amount'];
        $shippingAmount = $totals['shipping_amount'];
        $totalOrdered = $totals['base_grand_total'];       

        $order = array("sumInCentsIncVat" => round($totalOrdered*100),
            "sumInCentsExcVat" => round(($baseExclTax + $shippingAmount)*100),
            "vatInCents" => round($taxAmount*100),
            "currency" => $this->_store->getCurrentCurrencyCode(),
            "referenceId" => $quoteTmp->getId());

        $items = $quoteTmp->getAllItems();
        
        $this->_logger->debug("items in quote .".count($items));
        $products = array();
        if($items){
            foreach($items as $item) {      
                $qty = $item->getQty();//((isset($opts['qty']))? intval($opts['qty']) : 1);
                array_push($products,array(
                    "id" => $item->getProductId(),
                    "quantity" => $qty,
                    "title" => $item->getName(),
                    "priceInCentsIncVat" => round($item['row_total_incl_tax']*100/$qty),
                    "priceInCentsExcVat" => round($item['row_total']*100/$qty),
                    "vatInCents" => round($item['tax_amount']*100/$qty),
                    "vatPercentage" => $item['tax_percent'],
                    "imageUrl" => $this->_store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product'.$item->getProduct()->getImage()
            ));
            }
        }

        $shipIncTax = $totals['base_shipping_incl_tax'];
        $shipVat = $totals['base_shipping_tax_amount'];
        $shipExcTax = $shipIncTax - $shipVat;
        if($shipExcTax != 0){
            $shipPercent = $shipVat / $shipExcTax * 100;
        }else{
            $shipPercent = 0;
        }

        if(!$shippingExtraProd){
         $shippingExtraProd = array(
            "id" => $this->_defaultShippingMethod,
            "quantity" => 1,
            "title" => __('Handling and Delivery'),
            "priceInCentsIncVat" => round($shipIncTax * 100),
            "priceInCentsExcVat" => round($shipExcTax * 100),
            "vatInCents" => round($shipVat * 100),
            "vatPercentage" => $shipPercent);
        }

        array_push($products,$shippingExtraProd);

        //Consumer
        $resolver = $this->_objectManager->get('Magento\Framework\Locale\Resolver');
        $locale = $resolver->getLocale();
        $email = $quoteTmp->getCustomerEmail();
        if($email) {
            $consumer = array("locale" => $locale, "email" => $email);//, "mobilePhoneNumber" => "");            
        }else{
            $consumer = array("locale" => $locale, "email" => "");//, "mobilePhoneNumber" => "");            
        }

        //Return URLs

        $returnUrls = array(
            "success" => $this->_store->getBaseUrl()."payapipages/returns/success" ,
            "cancel" => $this->_store->getBaseUrl()."payapipages/returns/cancelled" ,
            "failed" => $this->_store->getBaseUrl()."payapipages/returns/failure"   
            );

        $callbackUrl = $this->_store->getBaseUrl()."rest/V1/payapipages/callback";
        $jsonCallbacks = array(
                        "processing" => $callbackUrl,
                        "success" => $callbackUrl,
                        "failed" => $callbackUrl,
                        "chargeback" => $callbackUrl
                    );

        $res = array("order" => $order, "products" => $products, "consumer" => $consumer, "shippingAddress" => $shippingAddress, "returnUrls" => $returnUrls, "callbacks" => $jsonCallbacks);
        $this->_logger->debug(json_encode($res));
        return $res;
    }

	
	protected function getShippingFromIp($ip) {		
        if(!$ip || $ip == ""){
            $this->_logger->debug("No ip informed. getShippingFromIp");
            return null;
        }else{
            $visitorIp = $ip;
        }

        $this->_logger->debug($visitorIp);
        $url = "https://input.payapi.io/v1/api/fraud/ipdata/".$visitorIp;
        $curl = $this->_objectManager->get('\Magento\Framework\HTTP\Client\Curl');
        $curl->get($url);
        $response = json_decode($curl->getBody(), true);
        $this->_logger->debug(json_encode($response));
        $countryCode = null;
        $regionCode = '';
        if($response && isset($response['countryCode'])){
        	$countryCode = $response['countryCode'];
        	if(isset($response['regionCode'])) 
                $regionCode = $response['regionCode'];
            else $regionCode = "";
        	if(isset($response['postalCode'])) 
                $postalCode = $response['postalCode'];
            else $postalCode = "";
            $this->_logger->debug($countryCode);
        }
        if($countryCode != null){
       		return [
            'firstname'    => 'xxxxx', //address Details
            'lastname'     => 'xxxxx',
                    'street' => 'xxxxx',
                    'city' => 'xxxxx',
            'country_id' => $countryCode,
            'region' => $regionCode,
            'postcode' => $postalCode,
            'telephone' => '0',
            'fax' => '0',
            'save_in_address_book' => 0
        	];        
        }else{
            $this->_logger->debug("returns null getShippingFromIp");
        	return null;
        }
    }

}
?>