<?php
namespace Payapi\CheckoutPayment\Controller\Index;

use Magento\Framework\App\Action\Context;

class SecureFormGenerator extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        Context $context,
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Payapi\CheckoutPayment\Helper\SecureFormHelper $secureFormHelper,
        \Magento\Checkout\Model\Cart $currentCart,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,      
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $mutableScopeConfig
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->secureFormHelper  = $secureFormHelper;
        $this->currentCart       = $currentCart;
        $this->logger            = $logger;
        $this->context           = $context;
        $this->scopeConfig = $scopeConfig;
        $this->mutableScopeConfig = $mutableScopeConfig;
        parent::__construct($context);
    }

    public function execute()
    {
        $scopeConfig = $this->scopeConfig;
        if ($scopeConfig->getValue('general/region/state_required') != ""){
            $this->mutableScopeConfig->saveConfig("general/region/state_required", "",  
                \Magento\Framework\App\Config\ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 
                null);     
        }

        $result = $this->resultJsonFactory->create();
        if ($this->getRequest()->isAjax()) {
            $this->logger->debug("EXECUTE IS AJAX");
            $referenceQuoteId  = $this->getRequest()->getPostValue('referenceQuoteId');
            $shippingExtraProd = $this->getRequest()->getPostValue('shippingProduct', false);
            $checkoutAddress   = $this->getRequest()->getPostValue('checkoutAddress', false);
            $ipaddress         = $this->getRequest()->getPostValue('ipaddress');

            if (!$ipaddress || $ipaddress == "") {
                $ipaddress = $this->getRequest()->getClientIp();
            }

            if (!$referenceQuoteId) {
                $quote            = $this->currentCart->getQuote();
                $referenceQuoteId = $quote->getId();
            }

            if ($shippingExtraProd) {
                $shippingExtraProd["quantity"] = intval($shippingExtraProd["quantity"]);
                $shippingExtraProd["priceInCentsIncVat"] = intval($shippingExtraProd["priceInCentsIncVat"]);
                $shippingExtraProd["priceInCentsExcVat"] = intval($shippingExtraProd["priceInCentsExcVat"]);
                $shippingExtraProd["vatInCents"] = intval($shippingExtraProd["vatInCents"]);
                $shippingExtraProd["vatPercentage"] = floatval($shippingExtraProd["vatPercentage"]);
            }

            $secureformObject = $this->secureFormHelper->postSecureForm(
                $referenceQuoteId,
                $shippingExtraProd,
                $checkoutAddress,
                $ipaddress
            );

            return $result->setData(["url" => $this->secureFormHelper->getSecureFormPostUrl() , "data" => $this->secureFormHelper->getJWTSignedData($secureformObject)]);
        }
    }
}
