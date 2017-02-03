<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Payapi\CheckoutPayment\Model\Config;

class AllActiveShippingMethods extends \Magento\Shipping\Model\Config\Source\Allmethods
{
    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Shipping\Model\Config $shippingConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Shipping\Model\Config $shippingConfig
    ) {
        parent::__construct($scopeConfig,$shippingConfig);
    }

    /**
     * Return array of active carriers.
     *
     * @return array
     */
    public function toOptionArray()
    {
        return parent::toOptionArray(true);
    }

}