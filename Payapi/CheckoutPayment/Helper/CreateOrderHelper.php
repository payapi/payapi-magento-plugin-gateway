<?php
namespace Payapi\CheckoutPayment\Helper;

class CreateOrderHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $_customlogger;
    protected $_productRepository;
    protected $_orderFactory;
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
        \Payapi\CheckoutPayment\Logger\Logger $customlogger
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
        $this->_customlogger->debug("Create Mage Order ".json_encode($orderData));
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
            $this->_customlogger->debug("user registered!");
            $customer= $this->customerRepository->getById($customer->getEntityId());
            $cart->assignCustomer($customer); //Assign quote to customer
        }else{           
            $this->_customlogger->debug("user is GUEST!");
            $cart->setCustomerIsGuest(true);
        }
        $cart->setCustomerEmail($orderData['email']);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        //add items in quote
        foreach($orderData['items'] as $item){
            //$product = $this->_productFactory->create()->load(); //intval($item['product_id'])
            //$product = $objectManager->get('Magento\Catalog\Model\Product')->load($item['product_id']);
            $product = $this->_productRepository->getById(intval($item['product_id']),false,$store->getId());
            $this->_customlogger->debug($product->getName());
            $this->_customlogger->debug(json_encode($product));
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
        //$order_id = $this->cartManagementInterface->placeOrder($cart->getId());
        
        /*$order = $this->_orderFactory->create()->load($order_id);
        $invoices = $order->getInvoiceCollection();
        foreach ($invoices as $invoice){
            $invoice->delete();
        }
 
        $order->setState("pending")->setStatus("pending");
 
        $order->save();
*/
        $result = ['order_id' => '$order_id'];
        return json_encode($result);
/*




        $order->setEmailSent(0);
        $increment_id = $order->getRealOrderId();
        if($order->getEntityId()){
            $result['order_id']= $order->getRealOrderId();
        }else{
            $result=['error'=>1,'msg'=>'Your custom message'];
        }
        return $result;*/
    }
}