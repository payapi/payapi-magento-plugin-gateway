<?php
namespace Payapi\CheckoutPayment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class CustomConfigProvider implements ConfigProviderInterface
{

    public function __construct(
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Payapi\CheckoutPayment\Helper\CreateOrderHelper $helper,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {

        $this->logger          = $logger;
        $this->helper          = $helper;
        $this->quoteRepository = $quoteRepository;
        $this->paymentMethod   = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [
            'payment' => [
                'customPayment' => [
                    'payapi_api_key'   => $this->paymentMethod->getConfigData('payapi_api_key'),
                    'payapi_public_id' => $this->paymentMethod->getConfigData('payapi_public_id'),
                ],
            ],
        ];
        return $config;
    }
}
