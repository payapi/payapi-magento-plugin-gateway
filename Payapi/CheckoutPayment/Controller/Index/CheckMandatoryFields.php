<?php
namespace Payapi\CheckoutPayment\Controller\Index;

use Magento\Framework\App\Action\Context;

class CheckMandatoryFields extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Model\ProductFactory $productloader
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productloader = $productloader;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        if ($this->getRequest()->isAjax()) {
            $itemsIds  = $this->getRequest()->getPostValue('itemsIds');

            $jsonResponse = [];
            foreach ($itemsIds as $id) {
                $jsonResponse[$id] = 0;
                $product = $this->productloader->create()->load($id);
                $customOptions = $product->getProductOptionsCollection();
                if ($customOptions) {
                    foreach ($customOptions as $o) {
                        if ($o->getIsRequire()) {
                            // or another title of option
                            $jsonResponse[$id] = 1;
                            break;
                        }
                    }
                }
            }

            return $result->setData($jsonResponse);
        }
    }
}
