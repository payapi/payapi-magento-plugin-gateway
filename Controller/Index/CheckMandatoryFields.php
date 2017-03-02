<?php
namespace Payapi\CheckoutPayment\Controller\Index;

use Magento\Framework\App\Action\Context;

class CheckMandatoryFields extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Model\ProductFactory $productloader,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository
    ) {
        $this->resultJsonFactory   = $resultJsonFactory;
        $this->productloader       = $productloader;
        $this->stockItemRepository = $stockItemRepository;
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        if ($this->getRequest()->isAjax()) {
            $itemsIds = $this->getRequest()->getPostValue('itemsIds');

            $jsonResponse = [];
            foreach ($itemsIds as $id) {
                $jsonResponse[$id]    = [];
                $jsonResponse[$id][0] = 0; //Has mandatory
                $product              = $this->productloader->create()->load($id);
                $stockItem            = $this->stockItemRepository->get($id);
                $customOptions        = $product->getProductOptionsCollection();

                if ($customOptions) {
                    foreach ($customOptions as $o) {
                        if ($o->getIsRequire()) {
                            // or another title of option
                            $jsonResponse[$id][0] = 1;
                            break;
                        }
                    }
                }

                if ($stockItem) {
                    if (!$stockItem->getIsInStock()) {
                        $jsonResponse[$id][0] = 1; //Fill with mandatory fields to redirect to the product page
                        $jsonResponse[$id][1] = 0;
                    } else {
                        if ($stockItem->getMinSaleQty() > $stockItem->getQty()) {
                            $jsonResponse[$id][0] = 1; //Fill with mandatory fields to redirect to the product page
                        }
                        $jsonResponse[$id][1] = $stockItem->getMinSaleQty();
                    }
                }

            }

            return $result->setData($jsonResponse);
        }
    }
}
