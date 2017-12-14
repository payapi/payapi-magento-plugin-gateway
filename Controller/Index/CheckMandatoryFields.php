<?php
namespace Payapi\CheckoutPayment\Controller\Index;

use Magento\Framework\App\Action\Context;
use Payapi\PaymentsSdk\payapiSdk;

class CheckMandatoryFields extends \Magento\Framework\App\Action\Action
{
    public function __construct(
        Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Catalog\Model\ProductFactory $productloader,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\CatalogInventory\Model\Stock\StockItemRepository $stockItemRepository
    ) {
        $this->resultJsonFactory   = $resultJsonFactory;
        $this->productloader       = $productloader;
        $this->storeManager        = $storeManager;
        $this->stockItemRepository = $stockItemRepository;
        $this->sdk = new payapiSdk();
        $this->sdk->settings();
        
        parent::__construct($context);
    }

    public function getPriceInclTax($product)
    {
        $price = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        return $price;         
    }

    public function getCurrentCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
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


                if($product->getTypeId() == \Magento\Downloadable\Model\Product\Type::TYPE_DOWNLOADABLE && $product->getCustomAttribute('links_purchased_separately', false)) {
                        $jsonResponse[$id][0] = 1;                    
                }

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
                    if ($stockItem->getManageStock() && !$stockItem->getIsInStock()) {
                        $jsonResponse[$id][0] = 1; //Fill with mandatory fields to redirect to the product page
                        $jsonResponse[$id][1] = 0;
                    } else {
                        if ($stockItem->getManageStock() && (($stockItem->getMinSaleQty() > $stockItem->getQty()) || $stockItem->getQty() <= 0)) {
                            $jsonResponse[$id][0] = 1; //Fill with mandatory fields to redirect to the product page
                        }
                        $jsonResponse[$id][1] = $stockItem->getMinSaleQty();
                        $partialPay = $this->sdk->partialPayment($this->getPriceInclTax($product)*100, $this->getCurrentCurrencyCode());
                        $jsonResponse[$id][2] = ($partialPay && $partialPay['code']==200)?1:0;
                    }
                }

            }

            return $result->setData($jsonResponse);
        }
    }
}
