<?php
namespace Payapi\CheckoutPayment\Helper;

use \Firebase\JWT\JWT;
use \Magento\Catalog\Api\ProductRepositoryInterface;
use \Magento\Framework\App\Helper\Context;

class SecureFormHelper extends \Magento\Framework\App\Helper\AbstractHelper
{

    public function __construct(
        Context $context,
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Magento\Quote\Api\CartRepositoryInterface $cartRepositoryInterface,
        \Magento\Quote\Api\CartManagementInterface $cartManagementInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Api\AddressRepositoryInterface $addressRepository,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Quote\Model\Quote\Address\Rate $shippingRate,
        \Magento\Framework\Locale\Resolver $resolver,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\DataObject\Factory $objectFactory,
        \Magento\Checkout\Model\Cart $currentCart
    ) {
        $this->logger                  = $logger;
        $this->cartManagementInterface = $cartManagementInterface;
        $this->cartRepositoryInterface = $cartRepositoryInterface;
        $this->storeManager            = $storeManager;
        $this->productRepository       = $productRepository;
        $this->store                   = $this->storeManager->getStore();
        $this->shippingRate            = $shippingRate;
        $this->resolver                = $resolver;
        $this->curl                    = $curl;
        $this->objectFactory           = $objectFactory;
        $this->customerSession         = $currentCart->getCustomerSession();
        $this->scopeConfig             = $context->getScopeConfig();
        $this->addressRepository       = $addressRepository;

        $paymentMethod               = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");
        $this->defaultShippingMethod = $paymentMethod->getConfigData('instantbuy_shipping_method');
        $this->payapiApiKey          = $paymentMethod->getConfigData('payapi_api_key');
        $this->payapiPublicId        = $paymentMethod->getConfigData('payapi_public_id');
        $this->isStaging             = $paymentMethod->getConfigData('staging');

        parent::__construct($context);
    }

    public function postSecureForm(
        $referenceQuoteId,
        $shippingExtraProd = false,
        $checkoutAddress = false,
        $ipaddress = ""
    ) {
        $this->logger->debug("POST SECURE FORM");
        $cart_id        = $this->cartManagementInterface->createEmptyCart();
        $referenceQuote = $this->cartRepositoryInterface->get($referenceQuoteId);
        $newQuote       = $this->cartRepositoryInterface->get($cart_id);
        foreach ($referenceQuote->getAllVisibleItems() as $item) {
            $newItem = clone $item;
            $newQuote->addItem($newItem);
        }

        if ($referenceQuote->getCouponCode()) {
            $newQuote->setCouponCode($referenceQuote->getCouponCode());
        }

        $secureformData = $this->completeQuoteAndGetData($newQuote, $shippingExtraProd, $checkoutAddress, $ipaddress);
        return $secureformData;
    }

    public function getInstantBuySecureForm($productId, $opts = ['qty => 1'], $ipaddress = "")
    {
        $quote = $this->generateNewQuoteWithProduct($productId, $opts);
        if ($quote) {
            $secureformData = $this->completeQuoteAndGetData($quote, false, false, $ipaddress);
            return $secureformData;
        }

        return null;
    }

    private function generateNewQuoteWithProduct($productId, $opts = ['qty' => 1])
    {

        $product = $this->productRepository->getById($productId, false, $this->store->getId());

        if ($product) {
            $quoteId = $this->cartManagementInterface->createEmptyCart();
            $quote   = $this->cartRepositoryInterface->get($quoteId);
            $this->logger->debug(json_encode($opts));
            $optsObj = $this->objectFactory->create($opts);
            $quote->addProduct(
                $product,
                $optsObj
            );
        } else {
            $this->logger->debug("PRODUCT NOT FOUND " . $productId);
            return null;
        }

        return $quote;
    }

    private function completeQuoteAndGetData($quote, $shippingExtraProd, $checkoutAddress = false, $ipaddress = "")
    {
        $this->logger->debug("completeQuoteAndGetData");
        $quote->setStore($this->store);
        $quote->setCurrency();

        //Set customer if logged
        $customer = null;
        if ($this->customerSession->isLoggedIn()) {
            $customer = $this->customerSession->getCustomer();
            //$quote->assignCustomer($customer); //Assign quote to customer
            $quote->setCustomerEmail($customer->getEmail());
            $userAddressId = $customer->getDefaultShipping();
            if($userAddressId) {
                $userAddressObj = $this->addressRepository->getById($userAddressId); 
                $region = '';
                if($userAddressObj->getRegion() != null && $userAddressObj->getRegion()->getRegionCode() != null) {
                    $region = $userAddressObj->getRegion()->getRegionCode();
                }

                $userAddress = [
                    'firstname'            => $userAddressObj->getFirstname(), //address Details
                    'lastname'             => $userAddressObj->getLastname(),
                    'street'               => $userAddressObj->getStreet()[0],
                    'city'                 => $userAddressObj->getCity(),
                    'country_id'           => $userAddressObj->getCountryId(),
                    'region'               => $region,
                    'postcode'             => $userAddressObj->getPostcode(),
                    'telephone'            => $userAddressObj->getTelephone(),
                    'fax'                  => 0,
                    'save_in_address_book' => 0,
                ];
                $this->logger->debug("userAddress ". json_encode($userAddress));
            }else{
                $userAddress = null; 
            }
        } else {
            $quote->setCustomerIsGuest(true);
            $userAddress = null;
        }

        //Shipping Address
        //1. use checkout addr (Always in checkout)
        //2. use user addr if logged in (Instant buy or post without address)
        //3. use ip country
        //4. use store country
        if ($checkoutAddress) {
            $this->logger->debug("param Address");
            $finalAddr = $checkoutAddress;
        } elseif ($userAddress) {
            $this->logger->debug("user Address");
            $finalAddr = $userAddress;
        } else {
            $this->logger->debug("ipAddress");
            $finalAddr = $this->getShippingFromIp($ipaddress);
            if (!isset($finalAddr) || $finalAddr == null) {
                $this->logger->debug("store Country");
                $countryCode = $this->scopeConfig->getValue('general/store_information/country_id');
                $region      = $this->scopeConfig->getValue('general/store_information/region_id');
                $finalAddr   = [
                    'firstname'            => 'xxxxx', //address Details
                    'lastname'             => 'xxxxx',
                    'street'               => 'xxxxx',
                    'city'                 => 'xxxxx',
                    'country_id'           => $countryCode,
                    'region_id'            => $region,
                    'postcode'             => '*',
                    'telephone'            => '0',
                    'fax'                  => '0',
                    'save_in_address_book' => 0,
                ];

            }
        }

        $this->logger->debug("after final address: " . json_encode($finalAddr));

        $quote->getBillingAddress()->addData($finalAddr);
        $quote->getShippingAddress()->addData($finalAddr);

        $this->logger->debug("after add data");
        // Shipping method

        $shippingMethod = $this->setShippingInfo($quote->getShippingAddress(), $shippingExtraProd);

        $quote->setPaymentMethod('payapi_checkoutpayment_secure_form_post'); //payment method
        $quote->setInventoryProcessed(false);
        $quote->getPayment()->importData(['method' => 'payapi_checkoutpayment_secure_form_post']);
        $quote->collectTotals();

        if (!$shippingExtraProd) {
            $totals     = $quote->getShippingAddress()->getData();
            $shipIncTax = $totals['base_shipping_incl_tax'];
            $shipVat    = $totals['base_shipping_tax_amount'];
            $shipExcTax = $shipIncTax - $shipVat;
            if ($shipExcTax != 0) {
                $shipPercent = $shipVat / $shipExcTax * 100;
            } else {
                $shipPercent = 0;
            }
            $shippingExtraProd = [
                "id"                 => $shippingMethod,
                "quantity"           => 1,
                "title"              => __('Handling and Delivery'),
                "priceInCentsIncVat" => round($shipIncTax * 100),
                "priceInCentsExcVat" => round($shipExcTax * 100),
                "vatInCents"         => round($shipVat * 100),
                "vatPercentage"      => $shipPercent];
        } 

        $quote->getShippingAddress()->setFreeShipping(floatval($shippingExtraProd['priceInCentsIncVat']) == 0);

        $quote->save();

        return $this->getSecureFormData($quote, $finalAddr, $shippingExtraProd);
    }

    private function getValidShippingMethod(){
        return $this->defaultShippingMethod;
    }

    private function setShippingInfo($shippingAddress, $shippingExtraProdOrig)
    {
        $shippingMethod = $this->getValidShippingMethod();
        if ($shippingExtraProdOrig) {
            $shippingMethod = $shippingExtraProdOrig["id"];
        }

        $shippingRate = $this->shippingRate;
        $shippingRate
            ->setCode($shippingMethod)
            ->getPrice(1);

        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod($shippingMethod);

        return $shippingMethod;
    }

    private function getSecureFormData($quoteTmp, $shippingAddress = [], $shippingExtraProd)
    {
        $this->logger->debug("getSecureFormData");

        $items     = $quoteTmp->getAllItems();
        $products  = [];
        $isVirtual = true;
        if ($items) {
            foreach ($items as $item) {
                $isVirtual  = $isVirtual && $item->getIsVirtual();
                $qty        = $item->getQty();
                $products[] = [
                    "id"                 => $item->getProductId(),
                    "quantity"           => $qty,
                    "title"              => $item->getName(),
                    "priceInCentsIncVat" => round($item['row_total_incl_tax'] * 100 / $qty),
                    "priceInCentsExcVat" => round($item['row_total'] * 100 / $qty),
                    "vatInCents"         => round($item['tax_amount'] * 100 / $qty),
                    "vatPercentage"      => $item['tax_percent'],
                    "extraData"          => "quote=" . $quoteTmp->getId(),
                    "imageUrl"           => $this->store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $item->getProduct()->getData('thumbnail'),
                ];
            }
        }

        if (!$isVirtual) {
            $this->logger->debug("NOT Virtual");
            $totals = $quoteTmp->getShippingAddress()->getData();
        } else {
            $this->logger->debug("isVirtual");
            $totals = $quoteTmp->getBillingAddress()->getData();
        }

        $baseExclTax    = $totals['base_subtotal_with_discount'];
        $taxAmount      = $totals['tax_amount'];
        $shippingAmount = $totals['shipping_amount'];
        $totalOrdered   = $totals['base_grand_total'];

        $order = ["sumInCentsIncVat" => round($totalOrdered * 100),
            "sumInCentsExcVat"           => round(($baseExclTax + $shippingAmount) * 100),
            "vatInCents"                 => round($taxAmount * 100),
            "currency"                   => $this->store->getCurrentCurrencyCode(),
            "referenceId"                => $quoteTmp->getId(),
            "tosUrl"                     => $this->store->getBaseUrl() . "privacy-policy-cookie-restriction-mode"];

        $products[] = $shippingExtraProd;

        //Consumer
        $locale = str_replace("_", "-", $this->resolver->getLocale());
        $email  = $quoteTmp->getCustomerEmail();
        if ($email) {
            $consumer = ["locale" => $locale, "email" => $email];
        } else {
            $consumer = ["locale" => $locale, "email" => ""];
        }
        if ($shippingAddress && !empty($shippingAddress)) {
            $payapiShipping = [];
            if (isset($shippingAddress["firstname"]) && $shippingAddress["firstname"] != 'xxxxx') {
                $payapiShipping["countryCode"]   = $shippingAddress['country_id'];
                $payapiShipping["recipientName"] = $shippingAddress['firstname'] . ' ' . $shippingAddress['lastname'];
                $payapiShipping["streetAddress"] = $shippingAddress['street'];
                $payapiShipping["postalCode"]    = $shippingAddress['postcode'];
                $payapiShipping["city"]          = $shippingAddress['city'];
                if (isset($shippingAddress["region"])) {
                    $payapiShipping["stateOrProvince"] = $shippingAddress['region'];
                }

            }
        }
        //Return URLs

        $returnUrls = [
            "success" => $this->store->getBaseUrl() . "payapipages/returns/success",
            "cancel"  => $this->store->getBaseUrl() . "payapipages/returns/cancelled",
            "failed"  => $this->store->getBaseUrl() . "payapipages/returns/failure",
        ];

        $callbackUrl   = $this->store->getBaseUrl() . "rest/V1/payapipages/callback";
        $jsonCallbacks = [
            "processing" => $callbackUrl,
            "success"    => $callbackUrl,
            "failed"     => $callbackUrl,
            "chargeback" => $callbackUrl,
        ];

        $res = ["order" => $order,
            "products"      => $products,
            "consumer"      => $consumer,
            "returnUrls"    => $returnUrls,
            "callbacks"     => $jsonCallbacks];

        if (isset($payapiShipping) && !empty($payapiShipping)) {
            $res["shippingAddress"] = $payapiShipping;
        }

        $this->logger->debug(json_encode($res));
        return $res;
    }

    private function getShippingFromIp($ip)
    {
        if (!$ip || $ip == "") {
            return null;
        } else {
            $visitorIp = $ip;
        }

        $url = "https://input.payapi.io/v1/api/fraud/ipdata/" . $visitorIp;
        $this->curl->get($url);
        $response    = json_decode($this->curl->getBody(), true);
        $countryCode = null;
        $regionCode  = '';

        if ($response && isset($response['countryCode'])) {
            $countryCode = $response['countryCode'];
            if (isset($response['regionCode'])) {
                $regionCode = $response['regionCode'];
            } else {
                $regionCode = "";
            }

            if (isset($response['postalCode'])) {
                $postalCode = $response['postalCode'];
            } else {
                $postalCode = "";
            }
        }

        if ($countryCode != null) {
            return [
                'firstname'            => 'xxxxx', //address Details
                'lastname'             => 'xxxxx',
                'street'               => 'xxxxx',
                'city'                 => 'xxxxx',
                'country_id'           => $countryCode,
                'region'               => $regionCode,
                'postcode'             => $postalCode,
                'telephone'            => '0',
                'fax'                  => '0',
                'save_in_address_book' => 0,
            ];
        } else {
            $this->logger->debug("returns null getShippingFromIp");
            return null;
        }
    }

    public function getJWTSignedData($payapiObject)
    {
        $this->logger->debug("getJWTSignedData");
        $strSigned = JWT::encode($payapiObject, $this->payapiApiKey);
        $this->logger->debug($strSigned);
        return $strSigned;
    }

    public function getSecureFormPostUrl()
    {
        $domain = (($this->isStaging) ? "https://staging-input.payapi.io" : "https://input.payapi.io");
        return $domain . '/v1/secureform/' . $this->payapiPublicId;
    }
}
