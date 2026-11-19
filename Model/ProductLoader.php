<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Loads products from database by IDs and sorts them strictly by API relevance score
 *
 * Further attributes (e.g. for observers of the autocomplete item event) can be added via the di.xml argument
 * additionalAttributes (attribute codes).
 *
 * @phpstan-import-type SearchHit from \NeuronaptiQ\Search\Api\SearchApiClientInterface
 * @phpstan-type ProductEntry array{product: Product, score: float, parent: Product|null}
 */
class ProductLoader
{
    private const ATTRIBUTES = [
        'name',
        'sku',
        'price',
        'special_price',
        'thumbnail',
        'small_image',
        'image',
        'url_key',
        'status',
        'visibility',
    ];

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $productCollectionFactory;

    /**
     * @var ProductStatus
     */
    private ProductStatus $productStatus;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var VariantUrlHash
     */
    private VariantUrlHash $variantUrlHash;

    /**
     * @var string[]
     */
    private array $attributes;

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param ProductStatus $productStatus
     * @param LoggerInterface $logger
     * @param VariantUrlHash $variantUrlHash
     * @param array $additionalAttributes Attribute codes loaded in addition to the default attributes
     * @phpstan-param array<mixed> $additionalAttributes
     */
    public function __construct(
        CollectionFactory $productCollectionFactory,
        ProductStatus $productStatus,
        LoggerInterface $logger,
        VariantUrlHash $variantUrlHash,
        array $additionalAttributes = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productStatus = $productStatus;
        $this->logger = $logger;
        $this->variantUrlHash = $variantUrlHash;

        $attributes = self::ATTRIBUTES;
        foreach ($additionalAttributes as $attributeCode) {
            if (is_string($attributeCode) && trim($attributeCode) !== '') {
                $attributes[] = trim($attributeCode);
            }
        }
        $this->attributes = array_values(array_unique($attributes));
    }

    /**
     * Loads products by ID from the database, retaining the exact ordering defined by the search score
     *
     * @param array $searchHits List of hits with 'id', 'score' and optionally 'parentId', 'options'
     * @param int|null $storeId
     * @return array List of entries with 'product' (Product), 'score' (float) and 'parent' (parent of a variant)
     * @phpstan-param list<SearchHit> $searchHits
     * @phpstan-return list<ProductEntry>
     */
    public function loadProductsByScore(array $searchHits, ?int $storeId = null): array
    {
        if (empty($searchHits)) {
            return [];
        }

        // Map hits by ID while preserving score and order
        $scoreById = [];
        $orderedIds = [];
        $parentIds = [];
        $hitDataById = [];

        foreach ($searchHits as $hit) {
            $id = (int)$hit['id'];
            $orderedIds[] = $id;
            $scoreById[$id] = (float)$hit['score'];
            $hitDataById[$id] = $hit;

            if (!empty($hit['parentId'])) {
                $parentId = (int)$hit['parentId'];
                $parentIds[] = $parentId;
            }
        }

        $allIds = array_values(array_unique(array_merge($orderedIds, $parentIds)));

        try {
            /** @var ProductCollection $collection */
            $collection = $this->productCollectionFactory->create();

            if ($storeId !== null) {
                $collection->addStoreFilter($storeId);
            }

            $collection->addAttributeToSelect($this->attributes);

            $collection->addIdFilter($allIds);
            $collection->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()]);
            $collection->addUrlRewrite();

            // Index loaded products by entity ID
            $loadedProducts = [];
            /** @var Product $product */
            foreach ($collection as $product) {
                $loadedProducts[(int)$product->getId()] = $product;
            }

            // Re-order loaded products strictly according to the score ranking from the API
            $orderedResults = [];
            foreach ($orderedIds as $productId) {
                if (!isset($loadedProducts[$productId])) {
                    continue;
                }

                $product = $loadedProducts[$productId];
                $hit = $hitDataById[$productId] ?? [];
                $score = $scoreById[$productId] ?? 0.0;
                $product->setData('relevance_score', $score);

                // Check if product is a variant of a parent configurable product
                $parentId = !empty($hit['parentId']) ? (int)$hit['parentId'] : null;
                $parentProduct = ($parentId && isset($loadedProducts[$parentId])) ? $loadedProducts[$parentId] : null;
                if ($parentId && $parentProduct === null) {
                    // Parent disabled or not in the website: variants are usually not visible individually,
                    // their own URL would end in a 404
                    continue;
                }

                $baseUrl = $parentProduct ? (string)$parentProduct->getProductUrl() : (string)$product->getProductUrl();

                // Hash for variant preselection on the product page (#attrId=optionId&...)
                $targetUrl = $this->variantUrlHash->append(
                    $baseUrl,
                    $this->variantUrlHash->build($hit['options'] ?? [])
                );

                $product->setData('target_url', $targetUrl);

                $orderedResults[] = [
                    'product' => $product,
                    'score' => $score,
                    'parent' => $parentProduct
                ];
            }

            return $orderedResults;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[NeuronaptiQ_Search] Failed to load products by score: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return [];
        }
    }
}
