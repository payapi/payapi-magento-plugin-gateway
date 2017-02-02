<?php
 
namespace Payapi\CheckoutPayment\Controller\Returns;
 
use Magento\Framework\App\Action\Context;
 
class Success extends \Magento\Framework\App\Action\Action
{
    protected $_resultPageFactory;
    protected $_logger;
    public function __construct(Context $context, 
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,        
        \Payapi\CheckoutPayment\Logger\Logger $logger
        )
    {
        $this->_resultPageFactory = $resultPageFactory;
        $this->_logger = $logger;
        parent::__construct($context);
    }
 
    public function execute()
    {
        $session = $this->_objectManager->get('Magento\Checkout\Model\Session');
        $quote = $session->getQuote();
        $session->clearQuote();
        
        $resultPage = $this->_resultPageFactory->create();
        return $resultPage;
    }
}