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

    public function contains($id){
      $ids = explode("_", $id);
      $options = $this->toOptionArray();
      if(isset($options[$ids[0]])){
        $list = $options[$ids[0]]['value'];
        foreach ($list as $method) {
         if($method['value'] == $id) return true;
        }
      }
      return false;
    }
}
