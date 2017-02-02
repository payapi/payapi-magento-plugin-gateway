<?php
namespace Payapi\Catalog\Block\Product;

class ListProduct extends \Magento\Catalog\Block\Product\ListProduct
{
    public function getProductDetailsHtml(\Magento\Catalog\Model\Product $product)
    {
        $html = $this->getLayout()->createBlock('Payapi\Catalog\Block\InstantBuyBlock')->setProduct($product)->setTemplate('Payapi_Catalog::productlistingitem.phtml')->toHtml();
        $renderer = $this->getDetailsRenderer($product->getTypeId());
        if ($renderer) {
            $renderer->setProduct($product);
            return $html.$renderer->toHtml();
        }
        return '';
    }
}
?>