<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Exception;

/**
 * Thrown when the NeuronaptiQ Search API returns no results
 *
 * The API also answers requests it classifies as bots with an empty result, so an empty result is not shown;
 * the caller falls back to the Magento search instead.
 */
class EmptySearchResultException extends SearchApiException
{
}
