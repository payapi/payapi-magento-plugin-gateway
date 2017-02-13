<?php
namespace Payapi\CheckoutPayment\Controller\Index;

use Magento\Framework\App\Action\Context;

class SecureFormGenerator extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Payapi\CheckoutPayment\Helper\SecureFormHelper $secureFormHelper,
        \Magento\Checkout\Model\Cart $currentCart
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->secureFormHelper  = $secureFormHelper;
        $this->currentCart       = $currentCart;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        if ($this->getRequest()->isAjax()) {
            $referenceQuoteId  = $this->getRequest()->getPostValue('referenceQuoteId');
            $shippingExtraProd = $this->getRequest()->getPostValue('shippingProduct');
            $checkoutAddress   = $this->getRequest()->getPostValue('checkoutAddress');
            $ipaddress         = $this->getRequest()->getPostValue('ipaddress');

            if (!$referenceQuoteId) {
                $quote            = $this->currentCart->getQuote();
                $referenceQuoteId = $quote->getId();
            }

            $secureformObject = $this->secureFormHelper->postSecureForm(
                $referenceQuoteId,
                $shippingExtraProd,
                $checkoutAddress,
                $ipaddress
            );

            return $result->setData($secureformObject);
        }
    }
}
