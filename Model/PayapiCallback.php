<?php
namespace Payapi\CheckoutPayment\Model;

use Payapi\CheckoutPayment\Api\PayapiCallbackInterface;
use \Firebase\JWT\JWT;

/**
 *  Defines the callback actions for async payments communication with PayApi
 */
class PayapiCallback implements PayapiCallbackInterface
{

    /**
     * Constructor
     * @param \YourNamespace\YourModule\Logger\Logger $logger
     */
    public function __construct(
        \Payapi\CheckoutPayment\Logger\Logger $logger,
        \Payapi\CheckoutPayment\Helper\CreateOrderHelper $helper,
        \Magento\Payment\Helper\Data $paymentHelper,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        $this->request         = $request;
        $this->logger          = $logger;
        $this->helper          = $helper;
        $this->quoteRepository = $quoteRepository;
        $paymentMethod         = $paymentHelper->getMethodInstance("payapi_checkoutpayment_secure_form_post");

        $this->payapiApiKey = $paymentMethod->getConfigData('payapi_api_key');
    }

    /**
     * Run PayApi callback actions.
     *
     * @api
     * @param string $data The encoded PayApi data
     * @return string The Callback response JSON.
     */
    public function callback($data)
    {
        $payload = $data;
        $this->logger->debug($payload);
        $decoded  = JWT::decode($payload, $this->payapiApiKey, ['HS256']);
        $jsonData = json_decode($decoded);
        $result   = null;

        if ($jsonData->payment->status == 'processing') {
            //Processing async payment
            $this->logger->debug("PROCESSING CALLBACK...");
            $order_id = $this->helper->createOrder($jsonData);
            $result   = ['platform' => 'magento', 'order_id' => $order_id];
        } else {
            $orderId = $this->getOrderId($jsonData);
            if ($orderId) {
                setcookie('fngrPayapiReturningConsumer', "", -1);
                if ($jsonData->payment->status == 'successful') {
                    $this->logger->debug("SUCCESSFUL CALLBACK...");
                    //Payment success
                    $order_id = $this->helper->addPayment($orderId);
                    $result   = ['platform' => 'magento', 'order_id' => $order_id];
                } elseif ($jsonData->payment->status == 'failed') {
                    //Payment failure
                    $this->logger->debug("FAILED CALLBACK...");
                    $order_id = $this->helper->changeStatus($orderId, "canceled", "canceled", "failed");
                    $result   = ['platform' => 'magento', 'order_id' => $order_id];
                } elseif ($jsonData->payment->status == 'cancelled') {
                    //Payment failure
                    $this->logger->debug("CANCELLED CALLBACK...");
                    $order_id = $this->helper->changeStatus($orderId, "canceled", "canceled", "cancelled");
                    $result   = ['platform' => 'magento', 'order_id' => $order_id]; 
                } elseif ($jsonData->payment->status == 'chargeback') {
                    $this->logger->debug("CHARGEBACK CALLBACK...");
                    $order_id = $this->helper->changeStatus(
                        $orderId,
                        "payment_review",
                        "payment_review",
                        "chargeback"
                    );

                    $result = ['platform' => 'magento', 'order_id' => $order_id];
                } else {
                    //Payment cancelled
                    $this->logger->debug("STATUS CALLBACK: ".$jsonData->payment->status);
                    $order_id = $this->helper->changeStatus(
                        $orderId,
                        "payment_review",
                        "payment_review",
                        $jsonData->payment->status
                    );

                    $result = ['platform' => 'magento', 'order_id' => $order_id];
                }
            }
        }

        if (isset($result)) {
            $this->logger->debug(json_encode($result));
            return json_encode($result);
        } else {
            $this->logger->debug("KO. COULD NOT CREATE ORDER OR ADD PAYMENT");
            throw new \Magento\Framework\Exception\LocalizedException(__('Could not create the order correctly.'));
        }
    }

    public function getOrderId($payapiObject)
    {
        $arr = explode('=', $payapiObject->products[0]->extraData);
        if (isset($arr) && count($arr) > 1 && $arr[0] == 'quote') {
            $quoteId = (int) $arr[1];
        } else {
            $quoteId = (int) ($payapiObject->order->referenceId);
        }

        $quote = $this->quoteRepository->get($quoteId);
        return $quote->getOrigOrderId();
    }
}
