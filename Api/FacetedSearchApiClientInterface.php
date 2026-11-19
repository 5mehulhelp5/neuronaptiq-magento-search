<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Api;

use NeuronaptiQ\Search\Exception\SearchApiException;
use NeuronaptiQ\Search\Model\Search\SearchFilters;

/**
 * Search for the search result page with the filters of the layered navigation and the facets (SimpKo/project#324)
 *
 * Separate interface, so SearchApiClientInterface stays unchanged for existing implementations.
 *
 * @phpstan-import-type SearchHit from SearchApiClientInterface
 * @phpstan-import-type SortOrder from SearchApiClientInterface
 * @phpstan-type FacetValue array{value: string, count: int}
 * @phpstan-type PriceBucket array{from: float, to: float, count: int}
 * @phpstan-type Facet array{
 *     group: string,
 *     values: list<FacetValue>,
 *     min?: float,
 *     max?: float,
 *     step?: float,
 *     ranges?: list<PriceBucket>
 * }
 * @phpstan-type FacetedSearchResult array{results: list<SearchHit>, total: int, facets: list<Facet>, sid?: string}
 */
interface FacetedSearchApiClientInterface
{
    /**
     * Executes the search for the search result page with filters and, if requested, facets
     *
     * Returns 'results' (hits with 'id', 'score' and optionally 'parentId', 'options'), 'total' and 'facets' (empty
     * if not requested or not supported by the search service). Facet values are the option ids, the group 'price'
     * has 'min', 'max', 'step' and 'ranges' (from inclusive, to exclusive), the group 'category' the category ids.
     *
     * @param string $query
     * @param int $page
     * @param int $limit
     * @param array $sort Optional keys 'key' and 'direction'
     * @param SearchFilters $filters
     * @param int|null $storeId
     * @return array
     * @phpstan-param SortOrder $sort
     * @phpstan-return FacetedSearchResult
     * @throws SearchApiException If endpoint/token are not configured, the API is unreachable or responds non-2xx
     */
    public function executeFacetedSearch(
        string $query,
        int $page,
        int $limit,
        array $sort,
        SearchFilters $filters,
        ?int $storeId = null
    ): array;
}
