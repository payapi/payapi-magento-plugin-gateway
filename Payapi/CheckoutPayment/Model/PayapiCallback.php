<?php
namespace Payapi\CheckoutPayment\Model;
use Payapi\CheckoutPayment\Api\PayapiCallbackInterface;
use Payapi\CheckoutPayment\Block\JWT\JWT;

 
class PayapiCallback implements PayapiCallbackInterface
{

    /**
     * Logging instance
     * @var \YourNamespace\YourModule\Logger\Logger
     */
    protected $_logger;
    protected $_helper;
    protected $_payapiApiKey;
    protected $_quoteRepository;
    protected $quote = false;

    /**
     * Constructor
     * @param \YourNamespace\YourModule\Logger\Logger $logger
     */
    public function __construct(
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Payapi\CheckoutPayment\Helper\CreateOrderHelper $helper,  
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {

        $this->_logger = $logger;
        $this->_helper = $helper;
        $this->_quoteRepository = $quoteRepository;
        $paymentMethod = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");

        $this->_payapiApiKey = $paymentMethod->getConfigData('payapi_api_key');
    }

    /**
     * Runs payapi callback
     *
     * @api
     * @return status 200.
     */   
    public function callback() {
        

        $body = file_get_contents("php://input");
        $body_json = json_decode($body);

        $payload = $body_json->data;
        $this->_logger->debug($payload);         
        $decoded = @JWT::decode($payload, $this->_payapiApiKey, array('HS256')); 
                
        $jsonData = json_decode($decoded);
        $result = null;
        if($jsonData->payment->status == 'processing'){
            //Processing async payment
            $order_id = $this->_helper->createOrder($jsonData);
            $result = json_encode(['order_id' => $order_id]);
            
        }else{
            $orderId = $this->getOrderId($jsonData);
            if($orderId){
                
                if($jsonData->payment->status == 'successful'){
                    //Payment success
                    $order_id = $this->_helper->addPayment($orderId);    
                    $result = json_encode(['order_id' => $order_id]);
                }else if($jsonData->payment->status == 'failed'){
                    //Payment failure
                    //restore stock
                    $order_id = $this->_helper->changeStatus($orderId,"canceled","canceled", "failed");     
                    $result = json_encode(['order_id' => $order_id]); 
                }else if($jsonData->payment->status == 'chargeback'){            
                    $order_id = $this->_helper->changeStatus($orderId,"payment_review","payment_review", "chargeback");     
                    $result = json_encode(['order_id' => $order_id]); 
                }else{
                    //Payment cancelled
                    //restore stock
                    $order_id = $this->_helper->changeStatus($orderId,"payment_review","payment_review", $jsonData->payment->status);     
                    $result = json_encode(['order_id' => $order_id]); 
                }
            }
        }
        if(isset($result)){        
            return $result;
        }else{
            throw new \Magento\Framework\Exception\LocalizedException(__('Could not create the order correctly.'));
        }
    }

    protected function getOrderId($payapiObject){
        parse_str($payapiObject->products[0]->extraData, $outParams);
        if(isset($outParams['quote'])){
            $quoteId = intval($outParams['quote']);
        }else{
            $quoteId = intval($payapiObject->order->referenceId);
        }

        $quote = $this->_quoteRepository->get($quoteId);
        return $quote->getOrigOrderId();
    }
}
