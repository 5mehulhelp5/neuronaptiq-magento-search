<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use NeuronaptiQ\Search\Model\TrackingService;

/**
 * Sends the customer data to the tracking endpoint on customer_login (also fired after registration)
 */
class TrackLoginObserver implements ObserverInterface
{
    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @var TrackingService
     */
    private TrackingService $trackingService;

    /**
     * @param StoreManagerInterface $storeManager
     * @param TrackingService $trackingService
     */
    public function __construct(StoreManagerInterface $storeManager, TrackingService $trackingService)
    {
        $this->storeManager = $storeManager;
        $this->trackingService = $trackingService;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $this->trackingService->trackLogin((int)$this->storeManager->getStore()->getId());
    }
}
