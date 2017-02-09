<?php
namespace Payapi\CheckoutPayment\Logger;

use Monolog\Logger;

class Handler extends \Magento\Framework\Logger\Handler\Base
{
    public function __construct(
        \Magento\Framework\Filesystem\DriverInterface $filesystem,
        $filePath = null
    ) {
        $this->loggerType = Logger::DEBUG;
        $this->fileName   = '/var/log/payapi.log';
        parent::__construct($filesystem, $filePath);
    }
}
