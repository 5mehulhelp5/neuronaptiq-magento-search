<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Api;

use NeuronaptiQ\Search\Exception\SearchApiException;

/**
 * Interface for external REST Search API client
 *
 * @phpstan-type SearchHit array{
 *     id: int,
 *     score: float,
 *     parentId?: int|null,
 *     options?: array<mixed>,
 *     categoryIds?: list<string>
 * }
 * @phpstan-type SearchPageResult array{results: list<SearchHit>, total: int, sid?: string}
 * @phpstan-type SortOrder array{key?: string, direction?: string}
 */
interface SearchApiClientInterface
{
    /**
     * Executes search query against the external REST API (Suggest)
     *
     * Returns an array of search hits, each containing at least 'id' (product entity id) and 'score' (relevance score).
     * The returned list must be sorted descending by relevance score.
     *
     * @param string $query
     * @param int $limit
     * @param int|null $storeId
     * @return array List of search hits
     * @phpstan-return list<SearchHit>
     * @throws SearchApiException If endpoint/token are not configured, the API is unreachable or responds non-2xx
     */
    public function search(string $query, int $limit = 5, ?int $storeId = null): array;

    /**
     * Executes search query for the search result page with pagination and sorting
     *
     * Returns an array with the keys 'results' (list of hits with 'id', 'score' and optionally
     * 'parentId', 'options'), 'total' (total hit count) and optionally 'sid'.
     *
     * @param string $query
     * @param int $page
     * @param int $limit
     * @param array $sort Optional keys 'key' and 'direction'
     * @param int|null $storeId
     * @return array
     * @phpstan-param SortOrder $sort
     * @phpstan-return SearchPageResult
     * @throws SearchApiException If endpoint/token are not configured, the API is unreachable or responds non-2xx
     */
    public function executeSearch(
        string $query,
        int $page = 1,
        int $limit = 20,
        array $sort = [],
        ?int $storeId = null
    ): array;
}
