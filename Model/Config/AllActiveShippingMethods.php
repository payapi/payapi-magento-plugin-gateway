<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Payapi\CheckoutPayment\Model\Config;

class AllActiveShippingMethods extends \Magento\Shipping\Model\Config\Source\Allmethods
{

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
