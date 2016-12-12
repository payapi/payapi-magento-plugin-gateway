<?php
namespace Payapi\CheckoutPayment\Helper;

class CreateOrderHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $_customlogger;
    protected $_productRepository;
    protected $_orderFactory;
    protected $_invoiceSender;
     /**
    * @param Magento\Framework\App\Helper\Context $context
    * @param Magento\Store\Model\StoreManagerInterface $storeManager
    * @param Magento\Catalog\Model\Product $product
    * @param Magento\Framework\Data\Form\FormKey $formKey $formkey,
    * @param Magento\Quote\Model\Quote $quote,
    * @param Magento\Customer\Model\CustomerFactory $customerFactory,
    * @param Magento\Sales\Model\Service\OrderService $orderService,
    */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Sales\Model\Service\OrderService $orderService,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Quote\Model\Quote\Address\Rate $shippingRate,
        \Payapi\CheckoutPayment\Logger\Logger $customlogger,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender
    ) {
        $this->_storeManager = $storeManager;
        $this->_productFactory = $productFactory;
        $this->quoteManagement = $quoteManagement;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->orderService = $orderService;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->shippingRate = $shippingRate;
        $this->_customlogger = $customlogger;
        $this->_productRepository = $productRepository;
        $this->_orderFactory = $orderFactory;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_invoiceSender = $invoiceSender;
        parent::__construct($context);
    }

    /**
     * Create Order On Your Store
     * 
     * @param array $orderData
     * @return array
     * 
    */
    public function createMageOrder($orderData) {
        $store=$this->_storeManager->getStore();
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        $customer=$this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($orderData['email']);// load customet by email address
        
        
         //init the quote
            $cart_id = $this->cartManagementInterface->createEmptyCart();
            $cart = $this->cartRepositoryInterface->get($cart_id);
            $cart->setStore($store);

            // if you have already buyer id then you can load customer directly
            $cart->setCurrency();

        if($customer->getEntityId()){            
            $customer= $this->customerRepository->getById($customer->getEntityId());
            $cart->assignCustomer($customer); //Assign quote to customer
        }else{           
            $cart->setCustomerIsGuest(true);
        }
        $cart->setCustomerEmail($orderData['email']);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        //add items in quote
        foreach($orderData['items'] as $item){
            $product = $this->_productRepository->getById(intval($item['product_id']),false,$store->getId());
            $cart->addProduct(
                $product,
                intval($item['qty'])
            );
        }
        //Set Address to quote @todo add section in order data for seperate billing and handle it
        $cart->getBillingAddress()->addData($orderData['shipping_address']);
        $cart->getShippingAddress()->addData($orderData['shipping_address']);
        // Collect Rates and Set Shipping & Payment Method
        $this->shippingRate
            ->setCode($orderData['shipping_method'])
            ->getPrice(1);
        $shippingAddress = $cart->getShippingAddress();
        //@todo set in order data
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($orderData['shipping_method']); //shipping method
        //$cart->getShippingAddress()->addShippingRate($this->rate);
        $cart->setPaymentMethod('payapi_checkoutpayment_secure_form_post'); //payment method
        //@todo insert a variable to affect the invetory
        $cart->setInventoryProcessed(false);
        // Set sales order payment
        $cart->getPayment()->importData(['method' => 'payapi_checkoutpayment_secure_form_post']);
        // Collect total and saeve
        $cart->collectTotals();
        // Submit the quote and create the order
        $cart->save();

        $cart = $this->cartRepositoryInterface->get($cart->getId());
        $order_id = $this->cartManagementInterface->placeOrder($cart->getId());
        
        return $order_id;
    }


    public function addPayment($orderId){
        $order = $this->_orderRepository->get($orderId);
        if($order && $order->canInvoice()) {
            $invoice = $this->_invoiceService->prepareInvoice($order);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->_transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
            $this->_invoiceSender->send($invoice);
            //send notification code

            $order->setState("processing")->setStatus("processing");
            $order->addStatusHistoryComment(
                __('Notified customer about invoice #%1.', $invoice->getId())
            )
            ->setIsCustomerNotified(true)
            ->save();
            $this->changeStatus($orderId, "processing","processing");
            return $orderId;
        }
        throw new \Magento\Framework\Exception\LocalizedException(__('Could not add Payment to the order.'));
    }

    public function changeStatus($orderId, $state,$status){
        $order = $this->_orderRepository->get($orderId);

        $order->setState($state)->setStatus($status);
 
        $order->save();
        return $orderId;
        
    }
    
}