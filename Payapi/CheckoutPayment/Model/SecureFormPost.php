<?php
/**
 * Payapi
 *
 * @category Payapi
 * @package Payapi_CheckoutPayment
 * @author Francisco Nieto <francisco@payapi.io>
 * @license http://choosealicense.com/licenses/mit/ MIT License
 */
namespace Payapi\CheckoutPayment\Model;

use Magento\Payment\Model\Method\AbstractMethod;

class SecureFormPost extends AbstractMethod
{
    const CODE = 'payapi_checkoutpayment_secure_form_post';

    protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canAuthorize                = true;

    protected $_payapiApiKey = false;
    protected $_payapiPublicId = false;

    protected $_countryFactory;
    protected $_curl;

    protected $_supportedCurrencyCodes = array('USD');
    
    /**
     * @var string
     */
    protected $_code = self::CODE;

     public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Framework\HTTP\Client\Curl $curl,
        array $data = array()
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );

        $this->_countryFactory = $countryFactory;
        $this->_curl = $curl;
        
        $this->_payapiApiKey = $this->getConfigData('payapi_api_key');
        $this->_payapiPublicId = $this->getConfigData('payapi_public_id');
        $this->_logger->debug("Starting class form francisco"); // log location: var/log/system.log
    }

/**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_logger->debug('some text or francisco');
        
        //throw new \Magento\Framework\Validator\Exception(__('Inside Stripe, throwing donuts :]'));

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        /** @var \Magento\Sales\Model\Order\Address $billing */
        $address = $order->getBillingAddress();


        $om = \Magento\Framework\App\ObjectManager::getInstance();
        $manager = $om->get('Magento\Store\Model\StoreManagerInterface');
        $store = $manager->getStore();

        //Order data
        try{
        $currencyCode = $store->getCurrentCurrencyCode();
        $baseExclTax = $order->getBaseSubtotal() - $order->getBaseDiscountAmount(); 
        $taxAmount = $order->getBaseTaxAmount();      
        $shippingAmount = $order->getShippingAmount();
        $totalOrdered = $order->getBaseGrandTotal();
        $quoteId = $order->getQuoteId();

        $jsonOrder = array("sumInCentsIncVat" => $totalOrdered*100,
            "sumInCentsExcVat" => ($baseExclTax + $shippingAmount)*100,
            "vatInCents" => $taxAmount*100,
            "currency" => $currencyCode,
            "referenceId" => $quoteId);

        $prods = $order->getAllItems();
        $jsonProducts = [];
        foreach($prods as $item){
            $_product = $om->get('Magento\Catalog\Model\Product')->load($item->getProductId());
            $imageUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA).'catalog/product'.$_product->getImage();            
            array_push($jsonProducts, array(
            "quantity" => $item->getQtyOrdered(),
            "title" => $item->getName(),
            "priceInCentsIncVat" => $item->getRowTotalInclTax()*100,
            "priceInCentsExcVat" => $item->getRowTotal()*100,
            "vatInCents" => $item->getTaxAmount()*100,
            "vatPercentage" => $item->getTaxPercent(),
            "imageUrl" => $imageUrl           
            ));           
        }

        //Customer data

        $jsonConsumer = array("email" => $order->getCustomerEmail());
            
        $jsonAddress = array("recipientName" => $address->getName(),               
                    "streetAddress" => $address->getStreetLine(1),
                    "streetAddress2" => $address->getStreetLine(2),
                    "postalCode" => $address->getPostcode(),
                    "city" => $address->getCity(),
                    "stateOrProvince" => $address->getRegion(),
                    "countryCode" => $address->getCountryId());
           
        //Return URLs

        $jsonReturnUrls = array(
            "success" => "https://192.168.2.144:8443/checkout/onepage/success/",
            "cancel" => "https://192.168.2.144:8443/checkout/onepage/failure/",
            "failed" => "https://192.168.2.144:8443/checkout/onepage/failure/"    
            );

        $jsonData = json_encode(array("order" => $jsonOrder, "products" => $jsonProducts, "consumer" => $jsonConsumer, "shippingAddress" => $jsonAddress, "returnUrls" => $jsonReturnUrls));
        
        $this->_logger->debug($jsonData);
        header("Location: http://www.google.es");
        //$url = 'http://192.168.2.144/saveLog';
        //$params = ['log' =>  $currencyCode." ".$baseIncTax." ".$baseTaxAmount." ".$totalPaid];
        //$this->_curl->post($url, $params);
        //response will contain the output in form of JSON string
        //$response = $this->_curl->getBody();
/*  
            //$charge = \Stripe\Charge::create($requestData);
            $payment
                ->setTransactionId($charge->id)
                ->setIsTransactionClosed(0);
*/
            

        } catch (\Exception $e) {
            $this->_logger->debug('Payment capturing error. PayApi');
            $this->_logger->error(__('Payment capturing error. PayApi'));
            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }

        return $this;
    }


    /**
     * Determine method availability based on quote amount and config data
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        $this->_logger->debug("isAvaliable Payapi");
        
        if (!$this->getConfigData('payapi_api_key') || !$this->getConfigData('payapi_public_id')) {
            return false;
        }

        return parent::isAvailable($quote);
    }

    /**
     * Availability for currency
     *
     * @param string $currencyCode
     * @return bool
     */
    public function canUseForCurrency($currencyCode)
    {
        if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            $this->_logger->debug("Payapi. currency is USD");
            return false;
        }
        return true;
    }
}