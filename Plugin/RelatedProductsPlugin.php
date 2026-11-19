<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Plugin;

use Magento\Catalog\Block\Product\ProductList\Related;
use Magento\Catalog\Model\Product;
use NeuronaptiQ\Search\Model\CrossSellingService;
use NeuronaptiQ\Search\Model\VariantUrlHash;

/**
 * Fills the "Related Products" block of the product page with NeuronaptiQ cross-sellings
 *
 * Falls back to the maintained related products if there are no cross-sellings. Links of cross-sold variants
 * get a URL hash that preselects the variant on the product page.
 */
class RelatedProductsPlugin
{
    /**
     * @var CrossSellingService
     */
    private CrossSellingService $crossSellingService;

    /**
     * @var VariantUrlHash
     */
    private VariantUrlHash $variantUrlHash;

    /**
     * @param CrossSellingService $crossSellingService
     * @param VariantUrlHash $variantUrlHash
     */
    public function __construct(CrossSellingService $crossSellingService, VariantUrlHash $variantUrlHash)
    {
        $this->crossSellingService = $crossSellingService;
        $this->variantUrlHash = $variantUrlHash;
    }

    /**
     * Replaces the related products with the cross-sellings, if available
     *
     * @param Related $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetItems(Related $subject, $result)
    {
        $product = $subject->getProduct();
        if (!$product instanceof Product) {
            return $result;
        }

        return $this->crossSellingService->getProducts($product) ?? $result;
    }

    /**
     * Adds the variant preselection hash to the URLs of cross-sold products
     *
     * Also applies to "Add to Cart" of configurable products, which redirects to the product page.
     *
     * @param Related $subject
     * @param string $result
     * @param Product $product
     * @param array $additional
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @phpstan-param array<string, mixed> $additional
     */
    public function afterGetProductUrl(Related $subject, $result, $product, $additional = [])
    {
        if (!$product instanceof Product || $result === '#') {
            return $result;
        }

        return $this->variantUrlHash->append(
            $result,
            $this->crossSellingService->getVariantHash((int)$product->getId())
        );
    }
}
