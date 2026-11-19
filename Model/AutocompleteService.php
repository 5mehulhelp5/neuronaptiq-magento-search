<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use NeuronaptiQ\Search\Api\SearchApiClientInterface;
use NeuronaptiQ\Search\Exception\SearchApiException;
use NeuronaptiQ\Search\Model\Autocomplete\CategorySuggestionBuilder;
use NeuronaptiQ\Search\Model\Autocomplete\ItemConverter;
use Magento\Framework\App\RequestInterface;
use Magento\Search\Model\Autocomplete\ItemInterface;
use Magento\Search\Model\QueryFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates suggestion search from external REST API to loaded product items
 *
 * @phpstan-import-type SearchHit from SearchApiClientInterface
 */
class AutocompleteService
{
    /**
     * @var SearchApiClientInterface
     */
    private SearchApiClientInterface $apiClient;

    /**
     * @var ProductLoader
     */
    private ProductLoader $productLoader;

    /**
     * @var ItemConverter
     */
    private ItemConverter $itemConverter;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var QueryFactory
     */
    private QueryFactory $queryFactory;

    /**
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var CategorySuggestionBuilder
     */
    private CategorySuggestionBuilder $categorySuggestionBuilder;

    /**
     * @param SearchApiClientInterface $apiClient
     * @param ProductLoader $productLoader
     * @param ItemConverter $itemConverter
     * @param Config $config
     * @param QueryFactory $queryFactory
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param CategorySuggestionBuilder $categorySuggestionBuilder
     */
    public function __construct(
        SearchApiClientInterface $apiClient,
        ProductLoader $productLoader,
        ItemConverter $itemConverter,
        Config $config,
        QueryFactory $queryFactory,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger,
        CategorySuggestionBuilder $categorySuggestionBuilder
    ) {
        $this->apiClient = $apiClient;
        $this->productLoader = $productLoader;
        $this->itemConverter = $itemConverter;
        $this->config = $config;
        $this->queryFactory = $queryFactory;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->categorySuggestionBuilder = $categorySuggestionBuilder;
    }

    /**
     * Executes suggest search and returns autocomplete items
     *
     * API errors are rethrown so the caller can fall back to the Magento search.
     *
     * @return ItemInterface[]
     * @throws SearchApiException
     */
    public function getSuggestItems(): array
    {
        $queryText = $this->getQueryText();
        if ($queryText === '') {
            return [];
        }

        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $limit = $this->config->getSuggestLimit($storeId);

            // Step 1: Query REST API
            $searchHits = $this->apiClient->search($queryText, $limit, $storeId);
            if (empty($searchHits)) {
                return [];
            }

            // Step 2: Load products from DB and preserve score sorting
            $orderedProductEntries = $this->productLoader->loadProductsByScore($searchHits, $storeId);
            if (empty($orderedProductEntries)) {
                return [];
            }

            // Step 3: Convert into Magento Autocomplete ItemInterface items
            $items = $this->itemConverter->convert($orderedProductEntries);

            // Step 4: Categories of the suggested products (they link to the category page, enhanced only)
            if ($this->config->isEnhancedSuggestionsEnabled($storeId)) {
                $items = array_merge($items, $this->buildCategorySuggestions($searchHits, $storeId));
            }

            return $items;
        } catch (SearchApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error(
                '[NeuronaptiQ_Search] Error during search suggest execution: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return [];
        }
    }

    /**
     * Builds the category suggestions, errors only drop the categories and keep the product suggestions
     *
     * @param array $searchHits
     * @param int $storeId
     * @return ItemInterface[]
     * @phpstan-param list<SearchHit> $searchHits
     */
    private function buildCategorySuggestions(array $searchHits, int $storeId): array
    {
        try {
            return $this->categorySuggestionBuilder->build($searchHits, $storeId);
        } catch (\Throwable $e) {
            $this->logger->error(
                '[NeuronaptiQ_Search] Building the category suggestions failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
            return [];
        }
    }

    /**
     * Resolves the search query string from request or query factory
     *
     * @return string
     */
    private function getQueryText(): string
    {
        $query = (string)$this->request->getParam('q', '');
        if ($query === '') {
            $query = (string)$this->queryFactory->get()->getQueryText();
        }
        return trim($query);
    }
}
