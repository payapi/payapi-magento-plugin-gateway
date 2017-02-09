<?php
 
namespace Payapi\CheckoutPayment\Controller\Returns;
 
use Magento\Framework\App\Action\Context;
 
class Cancelled extends \Magento\Framework\App\Action\Action
{
    public function __construct(Context $context, \Magento\Framework\View\Result\PageFactory $resultPageFactory)
    {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }
 
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        return $resultPage;
    }
}
