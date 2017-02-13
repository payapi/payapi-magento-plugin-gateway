<?php

namespace Payapi\CheckoutPayment\Controller\Returns;

use Magento\Framework\App\Action\Context;

class Success extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Checkout\Model\Cart $currentCart
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->currentCart       = $currentCart;
        parent::__construct($context);
    }

    public function execute()
    {
        $session = $this->currentCart->getCheckoutSession();
        $session->clearQuote();

        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}
