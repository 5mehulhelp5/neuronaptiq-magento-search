<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Autocomplete;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Search\Model\Autocomplete\ItemInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds category suggestions from the categories of the suggested products
 *
 * Each hit counts for its deepest active category in the store's category tree, weighted by the hit score.
 * The best categories are returned in the format of the Smile ElasticSuite category suggestions
 * (type, title, url, breadcrumb).
 *
 * @phpstan-import-type SearchHit from \NeuronaptiQ\Search\Api\SearchApiClientInterface
 */
class CategorySuggestionBuilder
{
    public const TYPE_CATEGORY = 'category';
    public const ROW_CLASS = 'neuronaptiq-suggest-item neuronaptiq-suggest-item--category';

    /**
     * Maximum number of category suggestions
     */
    public const LIMIT = 3;

    /**
     * Categories up to this level are the root catalog and the store root
     */
    private const ROOT_LEVEL = 1;

    /**
     * @var CollectionFactory
     */
    private CollectionFactory $collectionFactory;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var ItemFactory
     */
    private ItemFactory $itemFactory;

    /**
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ItemFactory $itemFactory
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        StoreManagerInterface $storeManager,
        ItemFactory $itemFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->storeManager = $storeManager;
        $this->itemFactory = $itemFactory;
    }

    /**
     * Builds the category suggestions for the given hits
     *
     * @param array $hits
     * @param int $storeId
     * @return ItemInterface[]
     * @phpstan-param list<SearchHit> $hits
     */
    public function build(array $hits, int $storeId): array
    {
        $categoryIds = [];
        foreach ($hits as $hit) {
            foreach ($hit['categoryIds'] ?? [] as $categoryId) {
                $categoryIds[$categoryId] = $categoryId;
            }
        }
        if (empty($categoryIds)) {
            return [];
        }

        $store = $this->storeManager->getStore($storeId);
        if (!$store instanceof Store) {
            return [];
        }

        $rootId = (int)$store->getRootCategoryId();
        $categories = $this->loadCategories(array_values($categoryIds), $storeId, $rootId, true);
        if (empty($categories)) {
            return [];
        }

        $scores = $this->scoreCategories($hits, $categories);
        arsort($scores);
        $best = array_slice(array_keys($scores), 0, self::LIMIT, true);

        $names = $this->loadAncestorNames(array_map(fn ($id) => $categories[$id], $best), $storeId, $rootId);

        $items = [];
        foreach ($best as $categoryId) {
            $category = $categories[$categoryId];
            $items[] = $this->itemFactory->create([
                'type' => self::TYPE_CATEGORY,
                'title' => (string)$category->getName(),
                'url' => (string)$category->getUrl(),
                'breadcrumb' => $this->getBreadcrumb($category, $names, $rootId),
                'num_results' => '',
                'row_class' => self::ROW_CLASS,
            ]);
        }

        return $items;
    }

    /**
     * Sums the hit scores per category, each hit counts for its deepest loaded category
     *
     * @param array $hits
     * @param Category[] $categories Categories by id
     * @return array<int, float>
     * @phpstan-param list<SearchHit> $hits
     * @phpstan-param array<int, Category> $categories
     */
    private function scoreCategories(array $hits, array $categories): array
    {
        $scores = [];
        foreach ($hits as $hit) {
            $deepest = null;
            foreach ($hit['categoryIds'] ?? [] as $categoryId) {
                $category = $categories[(int)$categoryId] ?? null;
                if ($category !== null && ($deepest === null || $category->getLevel() > $deepest->getLevel())) {
                    $deepest = $category;
                }
            }
            if ($deepest !== null) {
                $id = (int)$deepest->getId();
                $scores[$id] = ($scores[$id] ?? 0.0) + $hit['score'];
            }
        }

        return $scores;
    }

    /**
     * Loads the names of the ancestors of the given categories (below the store root)
     *
     * @param Category[] $categories
     * @param int $storeId
     * @param int $rootId
     * @return array<int, string>
     */
    private function loadAncestorNames(array $categories, int $storeId, int $rootId): array
    {
        $ancestorIds = [];
        foreach ($categories as $category) {
            foreach ($this->getAncestorIds($category, $rootId) as $ancestorId) {
                $ancestorIds[$ancestorId] = $ancestorId;
            }
        }
        if (empty($ancestorIds)) {
            return [];
        }

        $names = [];
        foreach ($this->loadCategories(array_values($ancestorIds), $storeId, $rootId, false) as $id => $ancestor) {
            $names[$id] = (string)$ancestor->getName();
        }

        return $names;
    }

    /**
     * Returns the names of the ancestors below the store root, from the top level down
     *
     * @param Category $category
     * @param string[] $names Category names by id
     * @param int $rootId
     * @return string[]
     * @phpstan-param array<int, string> $names
     */
    private function getBreadcrumb(Category $category, array $names, int $rootId): array
    {
        $breadcrumb = [];
        foreach ($this->getAncestorIds($category, $rootId) as $ancestorId) {
            if (isset($names[$ancestorId])) {
                $breadcrumb[] = $names[$ancestorId];
            }
        }

        return $breadcrumb;
    }

    /**
     * Returns the ids of the ancestors of the category below the store root
     *
     * @param Category $category
     * @param int $rootId
     * @return int[]
     */
    private function getAncestorIds(Category $category, int $rootId): array
    {
        $pathIds = array_map('intval', explode('/', (string)$category->getPath()));
        $rootPosition = array_search($rootId, $pathIds, true);
        if ($rootPosition === false) {
            return [];
        }

        // Without the store root and the category itself
        return array_slice($pathIds, $rootPosition + 1, -1);
    }

    /**
     * Loads active categories of the store's category tree by id
     *
     * @param array $ids
     * @param int $storeId
     * @param int $rootId
     * @param bool $withUrl
     * @return array<int, Category>
     * @phpstan-param array<int, int|string> $ids
     */
    private function loadCategories(array $ids, int $storeId, int $rootId, bool $withUrl): array
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addIdFilter($ids)
            ->addAttributeToFilter('is_active', '1')
            ->addAttributeToFilter('path', ['like' => '1/' . $rootId . '/%'])
            ->addAttributeToFilter('level', ['gt' => self::ROOT_LEVEL]);
        if ($withUrl) {
            $collection->addUrlRewriteToResult();
        }

        $categories = [];
        /** @var Category $category */
        foreach ($collection as $category) {
            $categories[(int)$category->getId()] = $category;
        }

        return $categories;
    }
}
