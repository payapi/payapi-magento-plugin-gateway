<?php
namespace Payapi\CheckoutPayment\Controller\Index;

use Magento\Framework\App\Action\Context;
use Payapi\PaymentsSdk\payapiSdk;

class CheckPartialPayments extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Model\ProductFactory $productloader,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Payapi\CheckoutPayment\Logger\Logger $logger
    ) {
        $this->resultJsonFactory   = $resultJsonFactory;
        $this->productloader       = $productloader;
        $this->storeManager        = $storeManager;
        $this->checkoutSession     = $checkoutSession;
        $this->logger              = $logger;
        $this->sdk = new payapiSdk();
        $this->sdk->settings();
        
        parent::__construct($context);
    }

    public function getPriceInclTax($product)
    {
        $price = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        return $price;         
    }

    public function getCurrentCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    } 

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $partialPay = [];
        if ($this->getRequest()->isAjax()) {
            $total = $this->getRequest()->getPostValue('total');

            if($total) {            
                $this->logger->debug("FROM TOTAL " . $total);
                $partialPay = $this->sdk->partialPayment($total, $this->getCurrentCurrencyCode()); 
                
            } else {
                //From cart
                $this->logger->debug("FROM CART");
                $quote = $this->checkoutSession->getQuote();
                $total = $quote->getGrandTotal();
                $currency =  $this->getCurrentCurrencyCode();
                $partialPay = $this->sdk->partialPayment($total*100, $currency); 
            }
            $this->logger->debug("PARTIAL PAY DATA " . json_encode($partialPay));            
        }
        return $result->setData($partialPay);
    }
}
