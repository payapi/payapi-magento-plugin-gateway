<?php

namespace Payapi\Catalog\Block\Product\View;

use Magento\Catalog\Block\Product\AbstractProduct;

class Secureform extends AbstractProduct
{

	public function getSecureFormData(){

		$_product = $this->getProduct();
		$om = \Magento\Framework\App\ObjectManager::getInstance();
		$manager = $om->get('Magento\Store\Model\StoreManagerInterface');
		$store = $manager->getStore();

		//Order data
		$currencyCode = $store->getCurrentCurrencyCode();
		$priceNoTaxes = $_product->getPriceInfo()->getPrice('final_price')->getAmount()->getBaseAmount() * 100;
		$priceWithTaxes = $_product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue() * 100;
		$percentage = round(($priceWithTaxes/$priceNoTaxes - 1.0) * 100);
		$inCents = round($priceNoTaxes / 100.0 * $percentage);


		$order = array("sumInCentsIncVat" => round($priceWithTaxes),
    		"sumInCentsExcVat" => round($priceNoTaxes),
    		"vatInCents" => $inCents,
    		"currency" => $currencyCode,
    		"referenceId" => "ref123");
		
		$products = array(array(
    		"quantity" => 1,
    		"title" => $_product->getName(),
    		"priceInCentsIncVat" => round($priceWithTaxes),
    		"priceInCentsExcVat" => round($priceNoTaxes),
    		"vatInCents" => $inCents,
    		"vatPercentage" => $percentage,
    		"imageUrl" => $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product'.$_product->getImage()
  		));

  		//Customer data

		$customerSession = $om->get('Magento\Customer\Model\Session');
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

		//Return URLs

		$returnUrls = array(
      		"success" => "https://192.168.2.144:8443/checkout/onepage/success/",
      		"cancel" => "https://192.168.2.144:8443/checkout/onepage/failure/",
      		"failed" => "https://192.168.2.144:8443/checkout/onepage/failure/"    
			);

		return json_encode(array("order" => $order, "products" => $products, "consumer" => $consumer, "shippingAddress" => $shippingAddress, "returnUrls" => $returnUrls));
		
	}

}