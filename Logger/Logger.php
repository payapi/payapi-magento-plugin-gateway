<?php
namespace Payapi\CheckoutPayment\Logger;

class Logger extends \Monolog\Logger
{
    public function isEnabled()
    {
        return true;
    }
}
