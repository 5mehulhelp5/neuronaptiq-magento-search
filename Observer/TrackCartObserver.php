<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Observer;

use Magento\Checkout\Model\Cart;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use NeuronaptiQ\Search\Model\TrackingService;

/**
 * Tracks cart changes (add, update, remove) on checkout_cart_save_after
 */
class TrackCartObserver implements ObserverInterface
{
    /**
     * @var TrackingService
     */
    private TrackingService $trackingService;

    /**
     * @param TrackingService $trackingService
     */
    public function __construct(TrackingService $trackingService)
    {
        $this->trackingService = $trackingService;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $cart = $observer->getEvent()->getData('cart');
        if ($cart instanceof Cart) {
            $this->trackingService->trackCart($cart->getQuote());
        }
    }
}
