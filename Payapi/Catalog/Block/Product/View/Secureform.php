<?php

namespace Payapi\Catalog\Block\Product\View;

use Magento\Catalog\Block\Product\AbstractProduct;

class Secureform extends AbstractProduct
{
	protected $_payapiPublicId;
	protected $_payapiApiKey;
    protected $_objectManager;
    protected $_store;
    protected $_secureformData;
    protected $_visitorIp = "";

    public function checkPayApiConfiguration(){
    	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    	$paymentHelper =  $objectManager->get('\Magento\Payment\Helper\Data');
        $paymentMethod = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");

        $this->_payapiApiKey = $paymentMethod->getConfigData('payapi_api_key');
        $this->_payapiPublicId = $paymentMethod->getConfigData('payapi_public_id');
        $this->_objectManager = $objectManager;
        $manager = $this->_objectManager->get('Magento\Store\Model\StoreManagerInterface');
        $this->_store = $manager->getStore();
        $this->_visitorIp = $this->getVisitorIp();
      

        $param = ['qty' => $this->getQty()];        
        $opts = $this->getMandatoryValues();
        if(count($opts) > 0){
            $param['options'] = $opts;
        }
        $this->_secureformData = $objectManager->get('Payapi\CheckoutPayment\Controller\Index\SecureFormGenerator')->getSecureFormData($this->getProduct()->getId(), $param, $this->_visitorIp );
  
        return isset($this->_payapiPublicId) && isset($this->_payapiApiKey);        
    }

    public function getQty(){
        if(isset($_GET["qty"]) && is_numeric($_GET["qty"]))
            return intval($_GET["qty"]);
        return 1;
    }

    public function getMandatoryValues(){
        if($this->getProduct()){
            $customOptions = $this->_objectManager->get('Magento\Catalog\Model\Product\Option')->getProductOptionCollection($this->getProduct());
            if($customOptions){
                if(isset($_GET['options'])){
                    return $_GET['options'];
                }
            }
        }
        return [];
    }

    public function getPublicId(){
        return $this->_payapiPublicId;
    }

    public function getSecureFormData(){
        if($this->_secureformData){
            return json_decode(json_encode($this->_secureformData));
        }
        return false;
    }

    public function checkMandatoryFields(){
        if($this->getProduct()){
            $customOptions = $this->_objectManager->get('Magento\Catalog\Model\Product\Option')->getProductOptionCollection($this->getProduct());
            if($customOptions){
                foreach ($customOptions as $o) {
                    if ($o->getIsRequire()) { // or another title of option
                        return 1;
                    }
                }
            }
        }
        return 0;
    }

    public function getExtraData(){
        /*$vals = $this->getMandatoryValues();
        return http_build_query($vals);*/
        return 'quote='.$this->getSecureFormData()->order->referenceId;        
    }

    public function getVisitorIp() { 
        if($this->_visitorIp != ""){
            return $this->_visitorIp;
        }

        if(isset($_GET['ip'])){
            return $_GET['ip'];
        }

        $ipaddress = '';
        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');        
        
        return $ipaddress;
    }

}