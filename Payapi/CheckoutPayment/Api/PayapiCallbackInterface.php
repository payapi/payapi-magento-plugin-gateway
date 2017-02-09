<?php
namespace Payapi\CheckoutPayment\Api;

interface PayapiCallbackInterface
{
    /**
     * Run PayApi callback actions.
     *
     * @api
     * @param string $data The encoded PayApi data
     * @return string The Callback response JSON.
     */
    public function callback($data);
}
