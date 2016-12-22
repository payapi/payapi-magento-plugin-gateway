<?php
namespace Payapi\CheckoutPayment\Model;
use Payapi\CheckoutPayment\Api\PayapiCallbackInterface;
use Payapi\CheckoutPayment\Block\JWT\JWT;

use Magento\Framework\App\Filesystem\DirectoryList;
 
class PayapiCallback implements PayapiCallbackInterface
{

    /**
     * Logging instance
     * @var \YourNamespace\YourModule\Logger\Logger
     */
    protected $_logger;
    protected $_helper;
    protected $filesystem;
    /**
     * Constructor
     * @param \YourNamespace\YourModule\Logger\Logger $logger
     */
    public function __construct(
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Payapi\CheckoutPayment\Helper\CreateOrderHelper $helper,
        \Magento\Framework\Filesystem $filesystem
    ) {
        $this->_logger = $logger;
        $this->_helper = $helper;
        $this->filesystem = $filesystem;

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
                
        $jsonData = json_decode($decoded);
        $result = null;
        if($jsonData->payment->status == 'processing'){

            //Processing async payment
            $order_id = $this->_helper->createMageOrder($this->translateModel($jsonData));            
            $result = json_encode(['order_id' => $order_id]);
            $this->writeToFile($jsonData->order->referenceId,$order_id);

        }else{
            sleep(1);
            $orderId = $this->getOrderIdFromFile($jsonData->order->referenceId);
            if($orderId){
                
                if($jsonData->payment->status == 'successful'){
                    //Payment success
                    $order_id = $this->_helper->addPayment($orderId);    
                    $result = json_encode(['order_id' => $order_id]);
                }else{
                    //Payment failure
                    $order_id = $this->_helper->changeStatus($orderId,"payment_review","payment_review");     
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
        if(isset($payapiObject->consumer->mobilePhoneNumber)){
            $telephone = $payapiObject->consumer->mobilePhoneNumber;
        }else{
            $telephone = "0";
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
            'telephone' => $telephone,
            'save_in_address_book' => 0
                 ],
        'items'=> $prodList
        ];
        return $tempOrder;   

    }

    protected function writeToFile($fileKey,$fileValue){
        $directory = $this->filesystem->getDirectoryWrite(
            DirectoryList::TMP
        );
        $directory->create();
        $tmpFileName = $directory->getAbsolutePath($fileKey);
        $file = fopen($tmpFileName, "a+"); 
        fwrite($file, $fileValue); 
        fclose($file); 
    }

    protected function getOrderIdFromFile($fileKey){
        $directory = $this->filesystem->getDirectoryWrite(
            DirectoryList::TMP
        );
        $directory->create();
        $tmpFileName = $directory->getAbsolutePath($fileKey);        
        $file = fopen($tmpFileName, "a+");         
        $size = filesize($tmpFileName); 
        $text = fread($file, $size);          
        fclose($file); 
        $directory->delete($directory->getRelativePath($tmpFileName));
        return $text;
    }
}