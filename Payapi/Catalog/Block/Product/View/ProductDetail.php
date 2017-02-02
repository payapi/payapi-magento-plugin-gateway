<?php

namespace Payapi\Catalog\Block\Product\View;

use Magento\Catalog\Block\Product\AbstractProduct;

class ProductDetail extends AbstractProduct
{
    protected $_objectManager;
    protected $_secureformData = false;
    protected $_visitorIp = "";

    public function __construct(\Magento\Catalog\Block\Product\Context $context,
        \Payapi\Catalog\Block\InstantBuyBlock $instantBuyBlock,
        array $data = [])
    {
        parent::__construct($context, $data);
        $this->_instantBuyBlock = $instantBuyBlock;
    }

    public function checkPayApiConfiguration(){        
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_objectManager = $objectManager;
        if($this->_instantBuyBlock->checkPayApiConfiguration()){
            $this->_visitorIp = $this->_instantBuyBlock->getVisitorIp();
            //Just generate metas if invoker has different domain
            if($this->_visitorIp != $this->_instantBuyBlock->getVisitorIp(false)){
                $param = ['qty' => $this->getQty()];        
                $opts = $this->getMandatoryValues();
                if(count($opts) > 0){
                    $param['options'] = $opts;
                }
                $this->_secureformData = $objectManager->get('Payapi\CheckoutPayment\Controller\Index\SecureFormGenerator')->getInstantBuySecureForm($this->getProduct()->getId(), $param, $this->_visitorIp );
            }
            return true;
        }
        return false;
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
        if($this->getSecureFormData()){
            return 'quote='.$this->getSecureFormData()->order->referenceId;        
        }
        return "";
    }

    public function getInstantBuyBlock(){
        return $this->_instantBuyBlock;
    }

}