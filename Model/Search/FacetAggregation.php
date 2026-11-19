<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Search;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\BucketInterface;
use Magento\Framework\Search\Response\Bucket;

/**
 * Aggregations of a NeuronaptiQ search result for the Magento layered navigation (SimpKo/project#324)
 *
 * The layered navigation asks for a bucket per filterable attribute (Fulltext\Collection::getFacetedData) and throws
 * an exception if it does not exist. Groups without facet (no hits, or a search service without facets) therefore
 * get an empty bucket: the filter is not shown.
 *
 * @implements \IteratorAggregate<string, BucketInterface>
 */
class FacetAggregation implements AggregationInterface, \IteratorAggregate
{
    /**
     * @var array<string, BucketInterface>
     */
    private array $buckets;

    /**
     * @param BucketInterface[] $buckets Buckets by name
     */
    public function __construct(array $buckets = [])
    {
        $this->buckets = $buckets;
    }

    /**
     * @inheritDoc
     */
    public function getBucket($bucketName)
    {
        return $this->buckets[$bucketName] ?? new Bucket((string)$bucketName, []);
    }

    /**
     * @inheritDoc
     */
    public function getBuckets()
    {
        return $this->buckets;
    }

    /**
     * @inheritDoc
     */
    public function getBucketNames()
    {
        return array_keys($this->buckets);
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->buckets);
    }
}
