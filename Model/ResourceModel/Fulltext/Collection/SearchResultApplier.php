<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\ResourceModel\Fulltext\Collection;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplierInterface;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Select;
use NeuronaptiQ\Search\Model\ApiSearchResultRegistry;

/**
 * Applies search results to the catalog search collection with exact score ordering
 */
class SearchResultApplier implements SearchResultApplierInterface
{
    /**
     * @var AbstractDb
     */
    private AbstractDb $collection;

    /**
     * @var SearchResultInterface
     */
    private SearchResultInterface $searchResult;

    /**
     * @var int
     */
    private int $size;

    /**
     * @var int
     */
    private int $currentPage;

    /**
     * @var ApiSearchResultRegistry
     */
    private ApiSearchResultRegistry $apiSearchResultRegistry;

    /**
     * @param AbstractDb $collection
     * @param SearchResultInterface $searchResult
     * @param int $size
     * @param int $currentPage
     * @param ApiSearchResultRegistry $apiSearchResultRegistry
     */
    public function __construct(
        AbstractDb $collection,
        SearchResultInterface $searchResult,
        int $size,
        int $currentPage,
        ApiSearchResultRegistry $apiSearchResultRegistry
    ) {
        $this->collection = $collection;
        $this->searchResult = $searchResult;
        $this->size = $size;
        $this->currentPage = $currentPage;
        $this->apiSearchResultRegistry = $apiSearchResultRegistry;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $items = $this->searchResult->getItems();
        if (empty($items)) {
            $this->collection->getSelect()->where('NULL');
            return;
        }

        // NeuronaptiQ API results are already sliced per limit and page, all other results are sliced here
        if (!$this->apiSearchResultRegistry->isApiResult($this->searchResult)) {
            $items = $this->sliceItems($items, $this->size, $this->currentPage);
        }

        $ids = [];
        foreach ($items as $item) {
            if ($item->getId()) {
                $ids[] = (int)$item->getId();
            }
        }

        if (empty($ids)) {
            $this->collection->getSelect()->where('NULL');
            return;
        }

        $orderList = implode(',', $ids);
        $this->collection->getSelect()
            ->where('e.entity_id IN (?)', $ids)
            ->reset(Select::ORDER)
            ->order(new \Zend_Db_Expr("FIELD(e.entity_id, $orderList)"));
    }

    /**
     * Slices items for results not provided by the NeuronaptiQ API
     *
     * @param DocumentInterface[] $items
     * @param int $size
     * @param int $currentPage
     * @return DocumentInterface[]
     */
    private function sliceItems(array $items, int $size, int $currentPage): array
    {
        if ($size !== 0) {
            $itemsCount = count($items);
            $maxAllowedPageNumber = (int)ceil($itemsCount / $size);
            if ($currentPage < 1) {
                $currentPage = 1;
            }
            if ($maxAllowedPageNumber > 0 && $currentPage > $maxAllowedPageNumber) {
                $currentPage = $maxAllowedPageNumber;
            }

            $offset = ($currentPage - 1) * $size;
            $items = array_slice($items, $offset, $size);
        }

        return $items;
    }
}
