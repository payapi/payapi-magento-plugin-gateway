<?php
namespace Payapi\CheckoutPayment\Api;
 
interface PayapiCallbackInterface
{
    /**
     * Run payapi callback
     *
     * @api
     * @return empty json. Status 200.
     */
    public function callback();
}
