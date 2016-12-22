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

  /*  protected $_isGateway                   = true;
    protected $_canCapture                  = true;
    protected $_canCapturePartial           = true;
    protected $_canRefund                   = true;
    protected $_canRefundInvoicePartial     = true;
    protected $_canAuthorize                = true;*/


    protected $_isOffline = true;

    protected $_payapiApiKey = false;
    protected $_payapiPublicId = false;
    
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
        
        $this->_payapiApiKey = $this->getConfigData('payapi_api_key');
        $this->_payapiPublicId = $this->getConfigData('payapi_public_id');
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
        /*if (!in_array($currencyCode, $this->_supportedCurrencyCodes)) {
            $this->_logger->debug("Payapi. currency  USD");
            return false;
        }*/
        //All currecy
        return true;
    }
}