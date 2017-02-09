<?php

namespace Payapi\Catalog\Block\Product\View;

use Magento\Catalog\Block\Product\AbstractProduct;

class ProductDetail extends AbstractProduct
{
    public function __construct(
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Magento\Catalog\Block\Product\Context $context,
        \Payapi\Catalog\Block\InstantBuyBlock $instantBuyBlock,
        \Payapi\CheckoutPayment\Controller\Index\SecureFormGenerator $secureFormGenerator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->instantBuyBlock     = $instantBuyBlock;
        $this->secureFormGenerator = $secureFormGenerator;
        $this->secureformData      = false;
        $this->visitorIp           = "";
        $this->logger              = $logger;
    }

    public function checkPayApiConfiguration()
    {
        if ($this->instantBuyBlock->checkPayApiConfiguration()) {
            $this->visitorIp = $this->instantBuyBlock->getVisitorIp();
            //Just generate metas if invoker has different domain
            //if ($this->visitorIp != $this->instantBuyBlock->getVisitorIp(false)) {
            $param = ['qty' => $this->getQty()];
            $opts  = $this->getMandatoryValues();
            if (!empty($opts)) {
                 $this->logger->debug("hasMandatoryValues 3");
                $param['options'] = $opts;
            }
             $this->logger->debug("Before ". json_encode($param). " -- ".$this->visitorIp);
            $this->secureformData = $this->secureFormGenerator->getInstantBuySecureForm(
                $this->getProduct()->getId(),
                $param,
                $this->visitorIp
            );
            // }
            return true;
        }
        return false;
    }

    public function getQty()
    {
        $val = $this->getRequest()->getQueryValue('qty');
        if ($val && is_numeric($val)) {
            return (int) ($val);
        }

        return 1;
    }

    public function getMandatoryValues()
    {
        if ($this->getProduct()) {
            $customOptions = $this->getProduct()->getProductOptionsCollection();
            if ($customOptions) {
                $this->logger->debug("Start getMandatoryValues 2");
                $val = $this->getRequest()->getQueryValue('options');
                if ($val) {
                    return $val;
                }
            }
        }
        return [];
    }

    public function getSecureFormData()
    {
        if ($this->secureformData) {
            return json_decode(json_encode($this->secureformData));
        }
        return false;
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

    public function getExtraData()
    {
        if ($this->getSecureFormData()) {
            return 'quote=' . $this->getSecureFormData()->order->referenceId;
        }
        return "";
    }

    public function getInstantBuyBlock()
    {
        return $this->instantBuyBlock;
    }
}
