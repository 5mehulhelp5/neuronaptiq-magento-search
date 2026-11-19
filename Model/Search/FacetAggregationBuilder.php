<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Search;

use Magento\Framework\Search\Response\Aggregation\Value;
use Magento\Framework\Search\Response\Bucket;
use NeuronaptiQ\Search\Api\FacetedSearchApiClientInterface;

/**
 * Converts the facets of the search API into the aggregations of the Magento layered navigation
 * (SimpKo/project#324)
 *
 * - attribute groups → bucket "<attribute code>_bucket", value = option id, metrics value and count
 * - category → bucket "category_bucket", value = category id
 * - price → bucket "price_bucket" with the ranges as "<from>_<to>" (from inclusive, to exclusive), like the dynamic
 *   price aggregation of Magento; the price filter needs at least two ranges
 *
 * @phpstan-import-type Facet from FacetedSearchApiClientInterface
 */
class FacetAggregationBuilder
{
    public const BUCKET_SUFFIX = '_bucket';
    public const GROUP_PRICE = 'price';
    public const GROUP_CATEGORY = 'category';

    /**
     * Builds the aggregations of the given facets
     *
     * @param array $facets
     * @return FacetAggregation
     * @phpstan-param list<Facet> $facets
     */
    public function build(array $facets): FacetAggregation
    {
        $buckets = [];
        foreach ($facets as $facet) {
            $name = $facet['group'] . self::BUCKET_SUFFIX;
            $values = $facet['group'] === self::GROUP_PRICE
                ? $this->buildPriceValues($facet['ranges'] ?? [])
                : $this->buildValues($facet['values']);
            $buckets[$name] = new Bucket($name, $values);
        }

        return new FacetAggregation($buckets);
    }

    /**
     * Values of an attribute or the category facet
     *
     * @param array $facetValues
     * @return Value[]
     * @phpstan-param list<array{value: string, count: int}> $facetValues
     */
    private function buildValues(array $facetValues): array
    {
        $values = [];
        foreach ($facetValues as $facetValue) {
            if ($facetValue['count'] > 0) {
                $values[] = new Value(
                    $facetValue['value'],
                    ['value' => $facetValue['value'], 'count' => $facetValue['count']]
                );
            }
        }

        return $values;
    }

    /**
     * Values of the price facet, one per range
     *
     * @param array $ranges
     * @return Value[]
     * @phpstan-param list<array{from: float, to: float, count: int}> $ranges
     */
    private function buildPriceValues(array $ranges): array
    {
        $values = [];
        foreach ($ranges as $range) {
            if ($range['count'] > 0) {
                $key = $this->formatNumber($range['from']) . '_' . $this->formatNumber($range['to']);
                $values[] = new Value($key, ['value' => $key, 'count' => $range['count']]);
            }
        }

        return $values;
    }

    /**
     * Number without trailing zeros, e.g. 30 instead of 30.0
     *
     * @param float $number
     * @return string
     */
    private function formatNumber(float $number): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $number), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
