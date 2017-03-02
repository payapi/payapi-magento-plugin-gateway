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
            $order_id = $this->helper->createOrder($jsonData);
            $result   = json_encode(['order_id' => $order_id]);
        } else {
            $orderId = $this->getOrderId($jsonData);
            if ($orderId) {
                if ($jsonData->payment->status == 'successful') {
                    //Payment success
                    $order_id = $this->helper->addPayment($orderId);
                    $result   = json_encode(['order_id' => $order_id]);
                } elseif ($jsonData->payment->status == 'failed') {
                    //Payment failure
                    $order_id = $this->helper->changeStatus($orderId, "canceled", "canceled", "failed");
                    $result   = json_encode(['order_id' => $order_id]);
                } elseif ($jsonData->payment->status == 'cancelled') {
                    //Payment failure
                    $order_id = $this->helper->changeStatus($orderId, "canceled", "canceled", "cancelled");
                    $result   = json_encode(['order_id' => $order_id]);                    
                } elseif ($jsonData->payment->status == 'chargeback') {
                    $order_id = $this->helper->changeStatus(
                        $orderId,
                        "payment_review",
                        "payment_review",
                        "chargeback"
                    );

                    $result = json_encode(['order_id' => $order_id]);
                } else {
                    //Payment cancelled
                    $order_id = $this->helper->changeStatus(
                        $orderId,
                        "payment_review",
                        "payment_review",
                        $jsonData->payment->status
                    );

                    $result = json_encode(['order_id' => $order_id]);
                }
            }
        }

        if (isset($result)) {
            return $result;
        } else {
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
