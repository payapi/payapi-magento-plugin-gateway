<?php
namespace Payapi\CheckoutPayment\Api;

interface SecureFormGeneratorInterface
{
    /**
     *  Interface method to get Instant buy payload for payapi
     *
     */
    public function getInstantBuySecureForm($productId, $opts, $ipaddress);
}
