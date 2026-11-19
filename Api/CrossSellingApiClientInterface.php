<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Api;

use NeuronaptiQ\Search\Exception\SearchApiException;

/**
 * Interface for the cross-selling endpoint of the NeuronaptiQ REST API
 *
 * @phpstan-import-type SearchHit from SearchApiClientInterface
 */
interface CrossSellingApiClientInterface
{
    /**
     * Returns the cross-selling hits for a product, sorted descending by score
     *
     * Hits may be variants, in that case 'parentId' and 'options' are set.
     *
     * @param string $productId
     * @param int $limit
     * @param int|null $storeId
     * @return array List of hits
     * @phpstan-return list<SearchHit>
     * @throws SearchApiException If endpoint/token are not configured, the API is unreachable or responds non-2xx
     */
    public function getCrossSellings(string $productId, int $limit, ?int $storeId = null): array;
}
