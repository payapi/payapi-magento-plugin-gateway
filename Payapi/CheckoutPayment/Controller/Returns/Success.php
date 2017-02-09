<?php

namespace Payapi\CheckoutPayment\Controller\Returns;

use Magento\Framework\App\Action\Context;

class Success extends \Magento\Framework\App\Action\Action
{

    public function __construct(
        Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Checkout\Model\Session $session
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->session            = $session;
        parent::__construct($context);
    }

    public function execute()
    {
        $quote   = $this->session->getQuote();
        $this->session->clearQuote();

        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}
