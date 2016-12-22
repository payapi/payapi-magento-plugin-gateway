<?php
namespace Payapi\CheckoutPayment\Controller\Index;

use Payapi\CheckoutPayment\Block\JWT\JWT;
use Magento\Framework\App\Action\Context;
use \Magento\Catalog\Api\ProductRepositoryInterface;

class SecureFormGenerator extends \Magento\Framework\App\Action\Action {
	protected $objectManager;
	protected $_payapiPublicId;
	protected $_payapiApiKey;
    protected $_logger;

	public function __construct(
	Context $context, 
    \Payapi\CheckoutPayment\Logger\Logger $logger,
	\Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory) {
    	$this->resultJsonFactory = $resultJsonFactory;
        $this->_logger = $logger;
        $this->_logger->debug("Execute SecureFormGenerator. Constructor");
    	parent::__construct($context);
	}
	
	public function execute() {
        $this->_logger->debug("Execute SecureFormGenerator");
    	$result = $this->resultJsonFactory->create();
    	if ($this->getRequest()->isAjax()) {

            $this->_logger->debug("Instant buy");              

        	$productId = $this->getRequest()->getPostValue('productId');
        	$qty = $this->getRequest()->getPostValue('qty',1);
            $this->_logger->debug($productId. " ---- ".$qty);
        	$secureformObject = $this->getEncodedSecureFormData($productId, $qty);
        	$this->_logger->debug($secureformObject);
        	return $result->setData($secureformObject);        	
    	}
    }

	
	protected function getEncodedSecureFormData($productId, $qty = 1){
		
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    	$paymentHelper =  $objectManager->get('\Magento\Payment\Helper\Data');
        $paymentMethod = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");

        $this->_payapiApiKey = $paymentMethod->getConfigData('payapi_api_key');
        $this->_payapiPublicId = $paymentMethod->getConfigData('payapi_public_id');
        

        $sfobject = $this->getSecureFormData($productId, $qty);
		$encoded =  JWT :: encode( $sfobject , $this->_payapiApiKey, 'HS512' ) ;
    	
    	return $encoded;    	
	}

	public function getPublicId(){
		return $this->_payapiPublicId;
	}

	protected function getSecureFormData($productId, $qty = 1){
        $this->_logger->debug("Params: ".$productId."--".$qty);
		$om = \Magento\Framework\App\ObjectManager::getInstance();

		$_productloader = $om->get('\Magento\Catalog\Model\ProductFactory');
        $this->_logger->debug("Loading product ".$productId);
        $_product = $_productloader->create()->load($productId);
        $this->_logger->debug(json_encode($_product));

		$manager = $om->get('Magento\Store\Model\StoreManagerInterface');		
		$store = $manager->getStore();

		$currencyCode = $store->getCurrentCurrencyCode();
		//Customer login
		$customerSession = $om->get('Magento\Customer\Model\Session');
		$customer = null;
		if($customerSession->isLoggedIn()) {
   			// customer login action
  			$customer =  $customerSession->getCustomer();
  			$address = $om->create('Magento\Customer\Model\Address')->load($customer->getDefaultBilling());
  			$consumer = array("email" => $customer->getEmail());
			if($address != null){
				$streetArr = $address->getStreet();
				$street1 = $streetArr[0];
				$street2 = (count($streetArr) > 1) ? $streetArr[1] : "";			

				$shippingAddress = array("recipientName" => $customer->getName(),      			
      				"streetAddress" => $street1,
      				"streetAddress2" => $street2,
      				"postalCode" => $address->getPostcode(),
      				"city" => $address->getCity(),
      				"stateOrProvince" => $address->getRegion(),
      				"countryCode" => $address->getCountry());
			}else{
	  			$shippingAddress = array("recipientName" => $customer->getName());
			}
		}else{
	  		$consumer = array("email" => "");
	  		$shippingAddress = array();
		}

		//Calculations	
		$quoteTmp = $this->getQuote($store, $_product, $customer, $qty);

		$totals = $quoteTmp->getShippingAddress()->getData();

		//Order data				
        $baseExclTax = $totals['base_subtotal_with_discount'];
        $taxAmount = $totals['tax_amount'];
        $shippingAmount = $totals['shipping_amount'];
        $totalOrdered = $totals['base_grand_total'];       

		$order = array("sumInCentsIncVat" => round($totalOrdered*100),
    		"sumInCentsExcVat" => round(($baseExclTax + $shippingAmount)*100),
    		"vatInCents" => round($taxAmount*100),
    		"currency" => $currencyCode,
    		"referenceId" => "oneclick".$this->getRandomId());
		
		$items = $quoteTmp->getAllItems();
		$products = array();
		if($items){
			foreach($items as $item) {		
				array_push($products,array(
					"id" => $item->getProductId(),
    				"quantity" => $item->getQty(),
    				"title" => $item->getName(),
    				"priceInCentsIncVat" => round($item['row_total_incl_tax']*100),
    				"priceInCentsExcVat" => round($item['row_total']*100),
    				"vatInCents" => round($item['tax_amount']*100),
    				"vatPercentage" => $item['tax_percent'],
    				"imageUrl" => $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product'.$_product->getImage()
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

  		array_push($products, array(
  			"id" => "oneclickshipping_oneclickshipping",
    		"quantity" => 1,
    		"title" => __('Shipping & Handling'),
    		"priceInCentsIncVat" => round($shipIncTax * 100),
    		"priceInCentsExcVat" => round($shipExcTax * 100),
    		"vatInCents" => round($shipVat * 100),
    		"vatPercentage" => $shipPercent));

  		
		//Return URLs

		$returnUrls = array(
      		"success" => $store->getBaseUrl()."payapipages/returns/success" ,
            "cancel" => $store->getBaseUrl()."payapipages/returns/cancelled" ,
            "failed" => $store->getBaseUrl()."payapipages/returns/failure"   
			);

		$callbackUrl = $store->getBaseUrl()."rest/V1/payapipages/callback";
        $jsonCallbacks = array(
                        "processing" => $callbackUrl,
                        "success" => $callbackUrl,
                        "failed" => $callbackUrl,
                        "chargeback" => $callbackUrl
                    );

		return array("order" => $order, "products" => $products, "consumer" => $consumer, "shippingAddress" => $shippingAddress, "returnUrls" => $returnUrls, "callbacks" => $jsonCallbacks);
		
	}



	protected function getQuote($store, $product, $customer = null, $qty = 1){
		
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->objectManager = $objectManager;
		$cartRepositoryInterface = $objectManager->get('Magento\Quote\Api\CartRepositoryInterface');
        $cartManagementInterface = $objectManager->get('Magento\Quote\Api\CartManagementInterface');

        $shippingRate = $objectManager->get('\Magento\Quote\Model\Quote\Address\Rate');

        $websiteId = $store->getWebsiteId();
                
         //init the quote
            $cart_id = $cartManagementInterface->createEmptyCart();
            $cart = $cartRepositoryInterface->get($cart_id);
            $cart->setStore($store);

            // if you have already buyer id then you can load customer directly
            $cart->setCurrency();

        if($customer && $customer->getEntityId()){            
            $cart->assignCustomer($customer); //Assign quote to customer
			$cart->setCustomerEmail($customer->getEmail());
			$tmpShipp = $om->create('Magento\Customer\Model\Address')->load($customer->getDefaultShipping());			
        }else{           
            $cart->setCustomerIsGuest(true);
			$tmpShipp = null;
        }

        if($tmpShipp == null){
        	$tmpShipp = $this->getShippingFromIp();
        	if(!isset($tmpShipp) || $tmpShipp == null){
        		$scopeConfig = $objectManager->get('\Magento\Framework\App\Config\ScopeConfigInterface');
        		$countryCode = $scopeConfig->getValue('general/store_information/country_id');
        		$region = $scopeConfig->getValue('general/store_information/region');
        		$tmpShipp = [
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
        //add items in quote
        
            $cart->addProduct(
                $product,
                $qty
            );
        
        //Set Address to quote @todo add section in order data for seperate billing and handle it
        $cart->getBillingAddress()->addData($tmpShipp);
        $cart->getShippingAddress()->addData($tmpShipp);
        // Collect Rates and Set Shipping & Payment Method
        $shippingRate
            ->setCode("oneclickshipping_oneclickshipping")
            ->getPrice(1);
        $shippingAddress = $cart->getShippingAddress();
        //@todo set in order data
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod("oneclickshipping_oneclickshipping"); //shipping method
        $cart->setPaymentMethod('payapi_checkoutpayment_secure_form_post'); //payment method
        $cart->setInventoryProcessed(false);
        $cart->getPayment()->importData(['method' => 'payapi_checkoutpayment_secure_form_post']);
        $quoteTmp = $cart->collectTotals();

        return $quoteTmp;        
	}

	protected function getRandomId(){
		$val = '';
		for( $i=0; $i<16; $i++ ) {
   			$val .= chr( rand( 65, 90 ) );
		}
		return $val;
	}

	protected function getShippingFromIp() {		
        $visitorIp = $this->getVisitorIp();
        $url = "https://input.payapi.io/v1/api/fraud/ipdata/".$visitorIp;
        $curl = $this->objectManager->get('\Magento\Framework\HTTP\Client\Curl');
        $curl->get($url);
        $response = json_decode($curl->getBody(), true);
        $countryCode = null;
        if($response && isset($response['countryCode'])){
        	$countryCode = $response['countryCode'];
        	$regionCode = $response['regionCode'];
        	$postalCode = $response['postalCode'];
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
        	return null;
        }
    }

    protected function getVisitorIp() {       
        $remoteAddress = $this->objectManager->create('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
        return $remoteAddress->getRemoteAddress();
    }
}
?>