<?php

namespace Payapi\Catalog\Block\Product\View;

class ProductListing extends \Magento\Framework\View\Element\Template {
	
	protected $_payapiPublicId;
	protected $_payapiApiKey;

    public function checkPayApiConfiguration(){
    	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    	$paymentHelper =  $objectManager->get('\Magento\Payment\Helper\Data');
        $paymentMethod = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");

        $this->_payapiApiKey = $paymentMethod->getConfigData('payapi_api_key');
        $this->_payapiPublicId = $paymentMethod->getConfigData('payapi_public_id');
        return isset($this->_payapiPublicId) && isset($this->_payapiApiKey);
    }

    public function getPublicId(){
        return $this->_payapiPublicId;
    }   

}
?>