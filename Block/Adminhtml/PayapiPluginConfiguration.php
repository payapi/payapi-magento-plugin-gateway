<?php

namespace Payapi\CheckoutPayment\Block\Adminhtml;

use Payapi\PaymentsSdk\payapiSdk;

class PayapiPluginConfiguration extends \Magento\Framework\View\Element\Template
{

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Payapi\CheckoutPayment\Model\Config\AllActiveShippingMethods $allShippingMethods,
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        array $data = []
    ) {
        $this->logger = $logger;
        $this->paymentHelper = $paymentHelper;
        $this->remoteAddress = $remoteAddress;
        $this->allShippingMethods = $allShippingMethods;
        $this->sdk = false;
        parent::__construct($context, $data);
    }

    public function checkPayApiConfiguration()
    {
        $paymentMethod                   = $this->paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");
        $this->payapiApiKey              = $paymentMethod->getConfigData('payapi_api_key');
        $this->payapiPublicId            = $paymentMethod->getConfigData('payapi_public_id');
        $this->instantBuyDefaultShipping = $paymentMethod->getConfigData('instantbuy_shipping_method');
        $this->isInstantBuyEnabled       = $paymentMethod->getConfigData('instantbuy_enabled');
        $this->isStaging                 = $paymentMethod->getConfigData('staging');
        $this->isEnabled                 = $paymentMethod->getConfigData('active');

        $checkOk = $this->isEnabled && isset($this->payapiPublicId) && isset($this->payapiApiKey) && isset($this->instantBuyDefaultShipping) && is_string($this->instantBuyDefaultShipping) && strlen($this->instantBuyDefaultShipping) > 0 && $this->allShippingMethods->contains($this->instantBuyDefaultShipping);
        if ($checkOk){            
            $config = ["debug" => $this->isStaging == '1'];
            if(!$this->sdk){
                $this->sdk = new payapiSdk($config, 'magento');
                //TODO MOVE TO ADMIN, WHEN PAYAPI DATA IS SAVED. REPLACE FOR SETTINGS()
            }
            // $staging = $this->isStaging == '1'; 
            // TODO ADD FIRST PARAMETER OF SETTINGS BOOLEAN IS STAGING/PRODUCTION
            $resp = $this->sdk->settings($this->payapiPublicId, $this->payapiApiKey);//, true); TO REFRESH                        
            return $resp['code'] === 200;
        }

        return false;
    }

    public function getPublicId()
    {
        return $this->payapiPublicId;
    }

    public function getApiKey()
    {
        return $this->payapiApiKey;
    }

    public function getIsInstantBuyEnabled(){
        return $this->isInstantBuyEnabled;
    }

    public function getIsStaging()
    {
        return $this->isStaging;
    }

    public function getVisitorIp($checkParams = true)
    {
        $ipaddress = '';
        $paramIp   = $this->getRequest()->getQueryValue('ip');
        if ($checkParams && $paramIp) {
            return $paramIp;
        } else {
            $ipaddress = $this->getRequest()->getClientIp();
        }
        return $ipaddress;
    }

    public function getPhpSdk(){
        return $this->sdk;
    }

    public function getPartner() {        
        $partnerId = 'somepay'; //'payapi'
        if (isset($this->sdk)) {
            return $this->sdk->plugin($partnerId);
        }
        return false;
    }
}
