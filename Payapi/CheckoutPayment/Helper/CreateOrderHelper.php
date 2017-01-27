<?php
namespace Payapi\CheckoutPayment\Helper;

class CreateOrderHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

    protected $_customlogger;
    protected $_orderFactory;
    protected $_invoiceSender;
    protected $_quoteRepository;
    protected $_stockItemRepository;
     /**
    * @param Magento\Framework\App\Helper\Context $context
    * @param Magento\Store\Model\StoreManagerInterface $storeManager
    * @param Magento\Catalog\Model\Product $product
    * @param Magento\Framework\Data\Form\FormKey $formKey $formkey,
    * @param Magento\Quote\Model\Quote $quote,
    * @param Magento\Customer\Model\CustomerFactory $customerFactory,
    */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Quote\Model\QuoteManagement $quoteManagement,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Payapi\CheckoutPayment\Logger\Logger $customlogger,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,        
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,  
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository
    ) {
        $this->_storeManager = $storeManager;
        $this->quoteManagement = $quoteManagement;
        $this->customerFactory = $customerFactory;
        $this->customerRepository = $customerRepository;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->_customlogger = $customlogger;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_quoteRepository = $quoteRepository;
        $this->_invoiceSender = $invoiceSender;
        $this->_stockItemRepository = $stockItemRepository;
        parent::__construct($context);
    }

    public function addPayment($orderId){
        $this->_customlogger->debug("Adding payment to order ".$orderId);
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
            if($order->getState() !== "holded"){
                $this->changeStatus($orderId, "processing","processing", "success");
            }else{
                $msg = __("Payment %1 event received. ","success");
                $order->addStatusHistoryComment($msg);
            }

            $order->addStatusHistoryComment(
                __('Notified customer about invoice #%1.', $invoice->getId())
            )
            ->setIsCustomerNotified(true)
            ->save();
            $this->_customlogger->debug("Added payment!");
            return $orderId;
        }
        throw new \Magento\Framework\Exception\LocalizedException(__('Could not add Payment to the order.'));
    }

    public function changeStatus($orderId, $state,$status, $payapiEvent){
        $order = $this->_orderRepository->get($orderId);

        $order->setState($state)->setStatus($status);
        $msg = __("Payment %1 event received. ",$payapiEvent);
        $order->addStatusHistoryComment($msg);
        
        $order->save();
        return $orderId;
        
    }

    protected function getShippingAddress($payapiObject){
        if(isset($payapiObject->consumer->mobilePhoneNumber)){
            $telephone = $payapiObject->consumer->mobilePhoneNumber;
        }else{
            $telephone = "0";
        }
        return [
            'firstname'    => $payapiObject->shippingAddress->recipientName, //address Details
            'lastname'     => '.',
            'street' => $payapiObject->shippingAddress->streetAddress,
            'street2' => $payapiObject->shippingAddress->streetAddress2,
            'city' => $payapiObject->shippingAddress->city,
            'country_id' => $payapiObject->shippingAddress->countryCode,
            'region' => $payapiObject->shippingAddress->stateOrProvince,
            'postcode' => $payapiObject->shippingAddress->postalCode,
            'telephone' => $telephone,
            'save_in_address_book' => 0
                 ];
    }
    
    public function createOrder($payapiObject){

        $this->_customlogger->debug("START CREATE ORDER: ".json_encode($payapiObject));
        $extra = $payapiObject->products[0]->extraData;
        $this->_customlogger->debug("START CREATE ORDER2: ".$extra);
        $merchantComment = "";
            if(isset($payapiObject->extraInputData) && isset($payapiObject->extraInputData->messageToMerchant)){
                $merchantComment = $payapiObject->extraInputData->messageToMerchant;
            }
        parse_str($extra, $extrasArr);
        if(isset($extrasArr['quote'])){
            //WEBSHOP/INSTANT BUY
            $quoteId = intval($extrasArr['quote']);    
            $this->_customlogger->debug("WEBSHOP QUOTEID: ".$quoteId);
            $this->quote = $this->_quoteRepository->get($quoteId); 
            $this->stockChanges = $this->checkStock($this->quote->getAllItems());
            return $this->saveOrder($this->quote,$payapiObject->consumer->email,$this->getShippingAddress($payapiObject), $merchantComment);

            //Update shipping address
        }else{
            //POST
            $quoteId = intval($payapiObject->order->referenceId);          
            $this->_customlogger->debug("POST QUOTEID: ".$quoteId);
            $this->quote = $this->_quoteRepository->get($quoteId);  
            $this->stockChanges = $this->checkStock($this->quote->getAllItems());
            
            return $this->saveOrder($this->quote, $payapiObject->consumer->email, false, $merchantComment);
        }
        
    }

    public function saveOrder($cart, $email, $shippingAddress = false, $messageToMerchant = ""){
        //Set Address to quote @todo add section in order data for seperate billing and handle it
       
        $this->_customlogger->debug("INIT SAVEORDER");
        $store=$this->_storeManager->getStore();
        $websiteId = $this->_storeManager->getStore()->getWebsiteId();
        $customer=$this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($email);// load customet by email address
        
        
            $cart->setStore($store);
            $cart->setCurrency();

        if($customer->getEntityId()){            
            $customer= $this->customerRepository->getById($customer->getEntityId());
            $cart->assignCustomer($customer); //Assign quote to customer
        }else{           
            $cart->setCustomerIsGuest(true);
        }
        $cart->setCustomerEmail($email);

        if($shippingAddress){
            $this->_customlogger->debug("ADDING SHIPPHING: ".json_encode($shippingAddress));
            $cart->getBillingAddress()->addData($shippingAddress);
            $cart->getShippingAddress()->addData($shippingAddress);
        }

       // $cart->collectTotals();
        // Submit the quote and create the order        
        $cart->save();



        
        $cart = $this->cartRepositoryInterface->get($cart->getId());
        $stockObj = $this->_stockItemRepository->get($cart->getAllItems()[0]->getProductId());
        $this->_customlogger->debug("final stock ".$stockObj->getQty()."Is processed inv: ".$cart->getInventoryProcessed());


        $order_id = $this->cartManagementInterface->placeOrder($cart->getId());
        
        
        $cart->setOrigOrderId($order_id);
        $cart->save();
        $this->_customlogger->debug("SAVED QUOTE WITH ORDER ID");
        $order = $this->_orderRepository->get($order_id);
        $msg = __("Payment %1 event received. ","processing");
        
        if($messageToMerchant && $messageToMerchant != ""){
            $msg = $msg. "Client msg: ".$messageToMerchant;
        }

        $order->addStatusHistoryComment($msg);
        if(count($this->stockChanges) > 0){
            $order->setState("holded")->setStatus("holded");
            foreach ($this->stockChanges as $stockMsg) {
                $order->addStatusHistoryComment('!! '.$stockMsg);
            }
        }

        $order->save();
        $this->_customlogger->debug("ORDER SAVED");
        return $order_id;
    }


    protected function checkStock($items){
        $result = [];
        $i=0;
        foreach($items as $item){
            $i = $i+1;
            $stockObj = $this->_stockItemRepository->get($item->getProductId());
            if($stockObj && $item->getQty() > $stockObj->getQty()){
                //No enough stock. Increment
                $original = $stockObj->getQty();
                $newQty = $item->getQty();

                $item->getProduct()->setStockData(['qty' => $newQty, 'is_in_stock' => $newQty]);
                $item->getProduct()->setQuantityAndStockStatus(['qty' => $newQty, 'is_in_stock' => $newQty]);
                $item->getProduct()->save();

                $this->_customlogger->debug("SAVED STOCK FOR ".$item->getProductId().":  ".$newQty);
                $result[] = __('Order item at line # %1 has a stock conflict. Original Qty in stock: # %2; Requested Qty: # %3',$i,$original,$newQty);
            }
        }

        return $result;
    }

    
}