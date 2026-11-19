<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use Magento\Framework\Api\Search\SearchResultInterface;

/**
 * Keeps track of search results that were provided by the NeuronaptiQ API (already paginated by the API)
 */
class ApiSearchResultRegistry
{
    /**
     * @var \WeakMap<SearchResultInterface, true>
     */
    private \WeakMap $results;

    /**
     * Initialize registry
     */
    public function __construct()
    {
        $this->results = new \WeakMap();
    }

    /**
     * Mark a search result as provided by the NeuronaptiQ API
     *
     * @param SearchResultInterface $searchResult
     * @return void
     */
    public function register(SearchResultInterface $searchResult): void
    {
        $this->results[$searchResult] = true;
    }

    /**
     * Check whether a search result was provided by the NeuronaptiQ API
     *
     * @param SearchResultInterface $searchResult
     * @return bool
     */
    public function isApiResult(SearchResultInterface $searchResult): bool
    {
        return isset($this->results[$searchResult]);
    }
}
