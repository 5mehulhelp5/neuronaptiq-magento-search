<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Config as CatalogConfig;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use NeuronaptiQ\Search\Api\CrossSellingApiClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides the NeuronaptiQ cross-sellings of a product as product collection for the "Related Products" block
 *
 * Variant hits are shown as their parent product, the variant is preselected via URL hash on the product page.
 *
 * @phpstan-import-type SearchHit from \NeuronaptiQ\Search\Api\SearchApiClientInterface
 */
class CrossSellingService
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var CrossSellingApiClientInterface
     */
    private CrossSellingApiClientInterface $apiClient;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var CatalogConfig
     */
    private CatalogConfig $catalogConfig;

    /**
     * @var Visibility
     */
    private Visibility $visibility;

    /**
     * @var StockHelper
     */
    private StockHelper $stockHelper;

    /**
     * @var VariantUrlHash
     */
    private VariantUrlHash $variantUrlHash;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Loaded collections per product id, null if there are no cross-sellings
     *
     * @var array<int, Collection|null>
     */
    private array $collections = [];

    /**
     * URL hashes for variant preselection per displayed product id
     *
     * @var array<int, string>
     */
    private array $variantHashes = [];

    /**
     * @param Config $config
     * @param CrossSellingApiClientInterface $apiClient
     * @param CollectionFactory $collectionFactory
     * @param CatalogConfig $catalogConfig
     * @param Visibility $visibility
     * @param StockHelper $stockHelper
     * @param VariantUrlHash $variantUrlHash
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        CrossSellingApiClientInterface $apiClient,
        CollectionFactory $collectionFactory,
        CatalogConfig $catalogConfig,
        Visibility $visibility,
        StockHelper $stockHelper,
        VariantUrlHash $variantUrlHash,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->apiClient = $apiClient;
        $this->collectionFactory = $collectionFactory;
        $this->catalogConfig = $catalogConfig;
        $this->visibility = $visibility;
        $this->stockHelper = $stockHelper;
        $this->variantUrlHash = $variantUrlHash;
        $this->logger = $logger;
    }

    /**
     * Returns the cross-selling products of the product, sorted by score
     *
     * Returns null if cross-sellings are disabled, the API fails or there are no results; the caller should then
     * show the maintained related products.
     *
     * @param ProductInterface $product
     * @return Collection|null
     */
    public function getProducts(ProductInterface $product): ?Collection
    {
        $productId = (int)$product->getId();
        if ($productId === 0) {
            return null;
        }

        if (!array_key_exists($productId, $this->collections)) {
            $this->collections[$productId] = $this->loadProducts($product, $productId);
        }

        return $this->collections[$productId];
    }

    /**
     * Returns the URL hash that preselects the cross-selling variant of a displayed product, or ''
     *
     * @param int $productId
     * @return string
     */
    public function getVariantHash(int $productId): string
    {
        return $this->variantHashes[$productId] ?? '';
    }

    /**
     * Queries the API and loads the cross-selling products
     *
     * @param ProductInterface $product
     * @param int $productId
     * @return Collection|null
     */
    private function loadProducts(ProductInterface $product, int $productId): ?Collection
    {
        $storeId = $product instanceof Product ? (int)$product->getStoreId() : null;
        if (!$this->config->isCrosssellingsEnabled($storeId)) {
            return null;
        }

        try {
            $hits = $this->apiClient->getCrossSellings(
                (string)$productId,
                $this->config->getCrosssellingsLimit($storeId),
                $storeId
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[NeuronaptiQ_Search] Cross-sellings of product %d failed: %s', $productId, $e->getMessage()),
                ['exception' => $e]
            );
            return null;
        }

        $displayIds = $this->resolveDisplayIds($hits, $productId);
        if (empty($displayIds)) {
            return null;
        }

        $collection = $this->createCollection($displayIds, $storeId);
        return $collection->count() > 0 ? $collection : null;
    }

    /**
     * Maps hits to the displayed product ids (parent of variants), removes duplicates and the product itself
     *
     * Remembers the variant hash of the best scored variant per parent.
     *
     * @param array $hits
     * @param int $productId
     * @return int[]
     * @phpstan-param list<SearchHit> $hits
     */
    private function resolveDisplayIds(array $hits, int $productId): array
    {
        $displayIds = [];
        foreach ($hits as $hit) {
            $displayId = !empty($hit['parentId']) ? (int)$hit['parentId'] : (int)$hit['id'];
            if ($displayId <= 0 || $displayId === $productId || isset($displayIds[$displayId])) {
                continue;
            }

            $displayIds[$displayId] = $displayId;
            if (!empty($hit['parentId'])) {
                $hash = $this->variantUrlHash->build($hit['options'] ?? []);
                if ($hash !== '') {
                    $this->variantHashes[$displayId] = $hash;
                }
            }
        }

        return array_values($displayIds);
    }

    /**
     * Creates the product collection like the core "Related Products" block, ordered like the given ids
     *
     * @param int[] $productIds
     * @param int|null $storeId
     * @return Collection
     */
    private function createCollection(array $productIds, ?int $storeId): Collection
    {
        $collection = $this->collectionFactory->create();
        if ($storeId !== null) {
            $collection->setStoreId($storeId);
        }

        $collection->addIdFilter($productIds)
            ->addStoreFilter($storeId)
            ->setVisibility($this->visibility->getVisibleInCatalogIds())
            ->addAttributeToSelect('required_options')
            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addUrlRewrite();
        $this->stockHelper->addIsInStockFilterToCollection($collection);

        $collection->getSelect()->order(
            new \Zend_Db_Expr(sprintf('FIELD(e.entity_id, %s)', implode(',', array_map('intval', $productIds))))
        );
        $collection->load();

        foreach ($collection as $item) {
            $item->setDoNotUseCategoryId(true);
        }

        return $collection;
    }
}
