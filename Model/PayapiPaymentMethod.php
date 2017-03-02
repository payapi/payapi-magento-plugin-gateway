<?php
/**
 * Payapi Payment Method
 */
namespace Payapi\CheckoutPayment\Model;

use Magento\Payment\Model\Method\AbstractMethod;

class PayapiPaymentMethod extends AbstractMethod
{
    const CODE = 'payapi_checkoutpayment_secure_form_post';

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        array $data = []
    ) {

        $this->_isOffline = true;
        $this->_code      = self::CODE;

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
        //All currecy
        return true;
    }
}
