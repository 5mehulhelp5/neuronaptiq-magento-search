<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Updates the updated_at timestamp of products without saving the whole product
 */
class ProductUpdatedAt
{
    private const TABLE = 'catalog_product_entity';

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param ResourceConnection $resourceConnection
     * @param DateTime $dateTime
     */
    public function __construct(ResourceConnection $resourceConnection, DateTime $dateTime)
    {
        $this->resourceConnection = $resourceConnection;
        $this->dateTime = $dateTime;
    }

    /**
     * Sets updated_at of the given products to the current time (UTC)
     *
     * @param int[] $productIds
     * @return void
     */
    public function touch(array $productIds): void
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (empty($productIds)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            $this->resourceConnection->getTableName(self::TABLE),
            ['updated_at' => $this->dateTime->gmtDate()],
            ['entity_id IN (?)' => $productIds]
        );
    }
}
