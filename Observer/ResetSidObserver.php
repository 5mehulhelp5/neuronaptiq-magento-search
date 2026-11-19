<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use NeuronaptiQ\Search\Model\Session\SidManager;

/**
 * Discards the SID of the search service on customer_logout
 *
 * Without this, guest orders and other customers after the logout would be assigned to the session of the
 * previous customer. On login the SID is kept, so searches made as guest stay linked to the following order.
 */
class ResetSidObserver implements ObserverInterface
{
    /**
     * @var SidManager
     */
    private SidManager $sidManager;

    /**
     * @param SidManager $sidManager
     */
    public function __construct(SidManager $sidManager)
    {
        $this->sidManager = $sidManager;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $this->sidManager->reset();
    }
}
