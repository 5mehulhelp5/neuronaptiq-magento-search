<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Observer;

use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use NeuronaptiQ\Search\Model\ResourceModel\ProductUpdatedAt;
use Psr\Log\LoggerInterface;

/**
 * Updates updated_at of the parent products when a variant (configurable child) or an associated product
 * of a grouped product is saved, on catalog_product_save_after
 */
class TouchParentProductsObserver implements ObserverInterface
{
    /**
     * @var Configurable
     */
    private Configurable $configurableType;

    /**
     * @var Grouped
     */
    private Grouped $groupedType;

    /**
     * @var ProductUpdatedAt
     */
    private ProductUpdatedAt $productUpdatedAt;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Configurable $configurableType
     * @param Grouped $groupedType
     * @param ProductUpdatedAt $productUpdatedAt
     * @param LoggerInterface $logger
     */
    public function __construct(
        Configurable $configurableType,
        Grouped $groupedType,
        ProductUpdatedAt $productUpdatedAt,
        LoggerInterface $logger
    ) {
        $this->configurableType = $configurableType;
        $this->groupedType = $groupedType;
        $this->productUpdatedAt = $productUpdatedAt;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $product = $observer->getEvent()->getData('product');
        if (!$product instanceof Product || !$product->getId()) {
            return;
        }

        $productId = (int)$product->getId();
        try {
            $parentIds = array_merge(
                $this->configurableType->getParentIdsByChild($productId),
                $this->groupedType->getParentIdsByChild($productId)
            );
            $this->productUpdatedAt->touch($parentIds);
        } catch (\Throwable $e) {
            // Never block saving the product because of the parent timestamp
            $this->logger->error(
                sprintf(
                    '[NeuronaptiQ_Search] Updating parent products of product %d failed: %s',
                    $productId,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }
    }
}
