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
    /**
     * Constructor
     * @param \YourNamespace\YourModule\Logger\Logger $logger
     */
    public function __construct(
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Payapi\CheckoutPayment\Helper\CreateOrderHelper $helper
    ) {
        $this->_logger = $logger;
        $this->_helper = $helper;
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

        $decoded = @JWT::decode($payload, 'qETkgXpgkhNKYeFKfxxqKhgdahcxEFc9', array('HS256'));        

        $this->_logger->debug(json_encode($decoded));
        $jsonData = json_decode($decoded);
        if($jsonData->payment->status == 'processing'){
            //Processing async payment
            $resul = $this->_helper->createMageOrder($this->translateModel($jsonData));
        }else if($decoded->payment->status == 'success'){
            //Payment success
        }else{
            //Payment failure
        }
        $this->_logger->debug($resul);         
        return $resul;
    }

    
    protected function translateModel($payapiObject){
      //  try{

        $prodList = array();
        $len = count($payapiObject->products);
        $shippingMethod = "";
        for ($i=0; $i < $len; $i++){            
            if($i == $len-1 && $len > 1){
                $shippingMethod = $payapiObject->products[$i]->id;
            }else{
                array_push($prodList, ['product_id'=>$payapiObject->products[$i]->id,'qty'=>$payapiObject->products[$i]->quantity]);
            }
        }
    $tempOrder=[
     'currency_id'  => $payapiObject->order->currency,
     'email'        => $payapiObject->consumer->email, //buyer email id
     'shipping_method' => $shippingMethod,
     'shipping_address' =>[
            'firstname'    => $payapiObject->shippingAddress->recipientName, //address Details
            'lastname'     => '.',
            'street' => $payapiObject->shippingAddress->streetAddress,
            'street2' => $payapiObject->shippingAddress->streetAddress2,
            'city' => $payapiObject->shippingAddress->city,
            'country_id' => $payapiObject->shippingAddress->countryCode,
            'region' => $payapiObject->shippingAddress->stateOrProvince,
            'postcode' => $payapiObject->shippingAddress->postalCode,
            'telephone' => '0',
            'save_in_address_book' => 0
                 ],
        'items'=> $prodList
        ];
        return $tempOrder;
      /*  }catch(Exception $e){
            $this->_logger->debug($e);
        }*/        

    }
}