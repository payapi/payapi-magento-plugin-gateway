<?php
namespace Payapi\CheckoutPayment\Helper;

class CreateOrderHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

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

        $this->storeManager            = $storeManager;
        $this->quoteManagement         = $quoteManagement;
        $this->customerFactory         = $customerFactory;
        $this->customerRepository      = $customerRepository;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->customlogger            = $customlogger;
        $this->orderRepository         = $orderRepository;
        $this->invoiceService          = $invoiceService;
        $this->transaction             = $transaction;
        $this->quoteRepository         = $quoteRepository;
        $this->invoiceSender           = $invoiceSender;
        $this->stockItemRepository     = $stockItemRepository;
        parent::__construct($context);
    }

    public function addPayment($orderId)
    {
        $this->customlogger->debug("Adding payment to order " . $orderId);
        $isHolded = false;
        $order    = $this->orderRepository->get($orderId);
        if ($order && $order->getState() == "holded") {
            $this->customlogger->debug("Was holded ");
            $order->setState("new")->setStatus("pending");
            $isHolded = true;
            $order->save();
        }
        if ($order && $order->canInvoice()) {

            $this->customlogger->debug("after holded ");
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->register();
            $this->customlogger->debug("after register ");
            $invoice->save();
            $this->customlogger->debug("after save ");
            $transactionSave = $this->transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $this->customlogger->debug("after addobject ");
            $transactionSave->save();
            $this->customlogger->debug("after save 2");
            $this->invoiceSender->send($invoice);
            $this->customlogger->debug("after send ");
            //send notification code
            
            $order->addStatusHistoryComment(
                __('Notified customer about invoice #%1.', $invoice->getId())
            )
                ->setIsCustomerNotified(true)
                ->save();

            if (!$isHolded) {
                $this->changeStatus($orderId, "processing", "processing", "success");
            } else {
                $this->customlogger->debug("IS HOLDED");
                $order->setState("holded")->setStatus("holded");
                $this->customlogger->debug("after set status");
                $msg = __("Payment %1 event received. ", "success");
                $order->addStatusHistoryComment($msg);
                $order->save();
            }

            return $orderId;
        }

        throw new \Magento\Framework\Exception\LocalizedException(__('Could not add Payment to the order.'));
    }

    public function changeStatus($orderId, $state, $status, $payapiEvent)
    {
        $order = $this->orderRepository->get($orderId);

        $order->setState($state)->setStatus($status);
        $msg = __("Payment %1 event received. ", $payapiEvent);
        $order->addStatusHistoryComment($msg);

        $order->save();
        return $orderId;
    }

    public function getShippingAddress($payapiObject)
    {
        if (isset($payapiObject->consumer->mobilePhoneNumber)) {
            $telephone = $payapiObject->consumer->mobilePhoneNumber;
        } else {
            $telephone = "0";
        }

        return [
            'firstname'            => $payapiObject->shippingAddress->recipientName, //address Details
            'lastname'             => '.',
            'street'               => $payapiObject->shippingAddress->streetAddress,
            'street2'              => $payapiObject->shippingAddress->streetAddress2,
            'city'                 => $payapiObject->shippingAddress->city,
            'country_id'           => $payapiObject->shippingAddress->countryCode,
            'region'               => $payapiObject->shippingAddress->stateOrProvince,
            'postcode'             => $payapiObject->shippingAddress->postalCode,
            'telephone'            => $telephone,
            'save_in_address_book' => 0,
        ];
    }

    public function createOrder($payapiObject)
    {

        $extra           = $payapiObject->products[0]->extraData;
        $merchantComment = "";
        if (isset($payapiObject->extraInputData)
            && isset($payapiObject->extraInputData->messageToMerchant)) {
            $merchantComment = $payapiObject->extraInputData->messageToMerchant;
        }

        $arr = explode('=', $extra);
        if (isset($arr) && count($arr) > 1 && $arr[0] == 'quote') {
            //WEBSHOP/INSTANT BUY
            $quoteId            = (int) ($arr[1]);
            $this->quote        = $this->quoteRepository->get($quoteId);
            $this->stockChanges = $this->checkStock($this->quote->getAllItems());
            return $this->saveOrder(
                $this->quote,
                $payapiObject->consumer->email,
                $this->getShippingAddress($payapiObject),
                $merchantComment
            );

            //Update shipping address
        } else {
            //POST
            $quoteId            = (int) ($payapiObject->order->referenceId);
            $this->quote        = $this->quoteRepository->get($quoteId);
            $this->stockChanges = $this->checkStock($this->quote->getAllItems());

            return $this->saveOrder($this->quote, $payapiObject->consumer->email, false, $merchantComment);
        }
    }

    public function saveOrder($cart, $email, $shippingAddress = false, $messageToMerchant = "")
    {
        $store     = $this->storeManager->getStore();
        $websiteId = $this->storeManager->getStore()->getWebsiteId();
        $customer  = $this->customerFactory->create();
        $customer->setWebsiteId($websiteId);
        $customer->loadByEmail($email); // load customet by email address

        $cart->setStore($store);
        $cart->setCurrency();

        if ($customer->getEntityId()) {
            $customer = $this->customerRepository->getById($customer->getEntityId());
            $cart->assignCustomer($customer); //Assign quote to customer
        } else {
            $cart->setCustomerIsGuest(true);
        }

        $cart->setCustomerEmail($email);

        if ($shippingAddress) {
            $cart->getBillingAddress()->addData($shippingAddress);
            $cart->getShippingAddress()->addData($shippingAddress);
        }

        // Submit the quote and create the order
        $cart->save();

        $cart     = $this->cartRepositoryInterface->get($cart->getId());
        $order_id = $this->cartManagementInterface->placeOrder($cart->getId());

        $cart->setOrigOrderId($order_id);
        $cart->save();
        $this->customlogger->debug("SAVED QUOTE WITH ORDER ID " . $order_id);
        $order = $this->orderRepository->get($order_id);
        $msg   = __("Payment %1 event received. ", "processing");

        if ($messageToMerchant && $messageToMerchant != "") {
            $msg = $msg . "Client msg: " . $messageToMerchant;
        }

        $order->addStatusHistoryComment($msg);
        if (!empty($this->stockChanges)) {
            $order->setState("holded")->setStatus("holded");
            foreach ($this->stockChanges as $stockMsg) {
                $order->addStatusHistoryComment('!! ' . $stockMsg);
            }
        }

        $order->save();
        return $order_id;
    }

    public function checkStock($items)
    {
        $result     = [];
        $i          = 0;
        $parentItem = false;
        foreach ($items as $item) {
            if ($item->getProduct()->getTypeId() != \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
                $i++;
                $qty = $item->getQty();
                if ($parentItem) {
                    $qty = $parentItem->getQty();
                }

                $stockObj = $this->stockItemRepository->get($item->getProductId());
                if ($stockObj && $qty > $stockObj->getQty()) {
                    $original = $stockObj->getQty();
                    $newQty   = $qty;

                    $this->setNewStock($item->getProduct(), $newQty);
                    $this->customlogger->debug("SAVED STOCK FOR " . $item->getProductId() . ":  " . $newQty);
                    $result[] = __(
                        'Order item at line # %1 has a stock conflict. Original Qty in stock: # %2; Requested Qty: # %3',
                        $i,
                        $original,
                        $newQty
                    );
                }
            } else {
                $parentItem = $item;
            }
        }

        return $result;
    }

    public function setNewStock($product, $newQty)
    {
        $product->setStockData(['qty' => $newQty, 'is_in_stock' => $newQty]);
        $product->setQuantityAndStockStatus(['qty' => $newQty, 'is_in_stock' => $newQty]);
        $product->save();
    }
}
