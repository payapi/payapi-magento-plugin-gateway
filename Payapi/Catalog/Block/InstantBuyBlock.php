<?php

namespace Payapi\Catalog\Block;

class InstantBuyBlock extends \Magento\Framework\View\Element\Template
{

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        array $data = []
    ) {
        $this->paymentHelper = $paymentHelper;
        $this->remoteAddress = $remoteAddress;
        parent::__construct($context, $data);
    }

    public function checkPayApiConfiguration()
    {
        $paymentMethod                   = $this->paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");
        $this->payapiApiKey              = $paymentMethod->getConfigData('payapi_api_key');
        $this->payapiPublicId            = $paymentMethod->getConfigData('payapi_public_id');
        $this->instantBuyDefaultShipping = $paymentMethod->getConfigData('instantbuy_shipping_method');

        return isset($this->payapiPublicId) && isset($this->payapiApiKey) && isset($this->instantBuyDefaultShipping) && is_string($this->instantBuyDefaultShipping) && strlen($this->instantBuyDefaultShipping) > 0;
    }

    public function getPublicId()
    {
        return $this->payapiPublicId;
    }

    public function getApiKey()
    {
        return $this->payapiApiKey;
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

    public function checkMandatoryFields()
    {
        if ($this->getProduct()) {
            $customOptions = $this->getProduct()->getProductOptionsCollection();
            if ($customOptions) {
                foreach ($customOptions as $o) {
                    if ($o->getIsRequire()) {
                        // or another title of option
                        return 1;
                    }
                }
            }
        }
        return 0;
    }
}
