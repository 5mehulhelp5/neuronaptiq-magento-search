<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\Api\Search\SearchCriteriaInterface;
use Magento\Framework\Api\Search\SearchResultFactory;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\App\RequestInterface;
use NeuronaptiQ\Search\Exception\EmptySearchResultException;
use NeuronaptiQ\Search\Exception\SearchApiException;
use NeuronaptiQ\Search\Model\Search\CriteriaFilterReader;
use NeuronaptiQ\Search\Model\Search\FacetAggregationBuilder;

/**
 * Adapter of the quick search for the Magento search (SearchInterface): builds a Magento SearchResult
 */
class MagentoSearchAdapter
{
    private const REQUEST_NAME_QUICK_SEARCH = 'quick_search_container';
    private const DEFAULT_LIMIT = 20;

    /**
     * @var SearchService
     */
    private SearchService $searchService;

    /**
     * @var SearchResultFactory
     */
    private SearchResultFactory $searchResultFactory;

    /**
     * @var DocumentFactory
     */
    private DocumentFactory $documentFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var ApiSearchResultRegistry
     */
    private ApiSearchResultRegistry $apiSearchResultRegistry;

    /**
     * @var CriteriaFilterReader
     */
    private CriteriaFilterReader $criteriaFilterReader;

    /**
     * @var FacetAggregationBuilder
     */
    private FacetAggregationBuilder $facetAggregationBuilder;

    /**
     * @param SearchService $searchService
     * @param SearchResultFactory $searchResultFactory
     * @param DocumentFactory $documentFactory
     * @param RequestInterface $request
     * @param ApiSearchResultRegistry $apiSearchResultRegistry
     * @param CriteriaFilterReader $criteriaFilterReader
     * @param FacetAggregationBuilder $facetAggregationBuilder
     */
    public function __construct(
        SearchService $searchService,
        SearchResultFactory $searchResultFactory,
        DocumentFactory $documentFactory,
        RequestInterface $request,
        ApiSearchResultRegistry $apiSearchResultRegistry,
        CriteriaFilterReader $criteriaFilterReader,
        FacetAggregationBuilder $facetAggregationBuilder
    ) {
        $this->searchService = $searchService;
        $this->searchResultFactory = $searchResultFactory;
        $this->documentFactory = $documentFactory;
        $this->request = $request;
        $this->apiSearchResultRegistry = $apiSearchResultRegistry;
        $this->criteriaFilterReader = $criteriaFilterReader;
        $this->facetAggregationBuilder = $facetAggregationBuilder;
    }

    /**
     * Executes the search via the NeuronaptiQ API
     *
     * Returns null if the request is not handled by NeuronaptiQ (other request types, empty query or a page size
     * above the API maximum), the caller should then use the Magento search.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultInterface|null
     * @throws EmptySearchResultException If the API finds nothing (also returned for bots), use the Magento search
     * @throws SearchApiException
     */
    public function search(SearchCriteriaInterface $searchCriteria): ?SearchResultInterface
    {
        // Only handle catalog quick search queries (not advanced search or catalog view categories)
        if ($searchCriteria->getRequestName() !== self::REQUEST_NAME_QUICK_SEARCH) {
            return null;
        }

        $queryText = $this->searchService->getQueryText();
        if ($queryText === '') {
            return null;
        }

        $pageSize = (int)$searchCriteria->getPageSize();
        $requestLimit = (int)$this->request->getParam('product_list_limit', 0);
        $limit = $requestLimit > 0 ? $requestLimit : ($pageSize > 0 ? $pageSize : self::DEFAULT_LIMIT);
        if ($limit > SearchService::MAX_LIMIT) {
            // More than the API can deliver per page (e.g. "show all"): not a page of the product list
            return null;
        }

        // Selection of the layered navigation, the facets become the aggregations of the layered navigation
        $result = $this->searchService->search(
            $queryText,
            $this->searchService->getPage(),
            $limit,
            $this->searchService->getSortOrder(),
            $this->criteriaFilterReader->read($searchCriteria)
        );

        $documents = [];
        foreach ($result['hits'] as $hit) {
            $documents[] = $this->documentFactory->create(['data' => ['id' => $hit['id'], 'score' => $hit['score']]]);
        }

        /** @var SearchResultInterface $searchResult */
        $searchResult = $this->searchResultFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($documents);
        $searchResult->setTotalCount($result['total']);
        $searchResult->setAggregations($this->facetAggregationBuilder->build($result['facets']));
        // Results are already paginated by the API, the result applier must not slice them again
        $this->apiSearchResultRegistry->register($searchResult);

        return $searchResult;
    }
}
