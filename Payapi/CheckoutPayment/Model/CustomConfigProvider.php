<?php
namespace Payapi\CheckoutPayment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;

class CustomConfigProvider implements ConfigProviderInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $paymentHelper =  $objectManager->get('\Magento\Payment\Helper\Data');
        $paymentMethod = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");
  
        $config = [
            'payment' => [
                'customPayment' => [
                    'payapi_api_key' => $paymentMethod->getConfigData('payapi_api_key'),
                    'payapi_public_id' => $paymentMethod->getConfigData('payapi_public_id')
                ]
            ]
        ];
        return $config;
    }
}