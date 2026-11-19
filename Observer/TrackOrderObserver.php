<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use NeuronaptiQ\Search\Model\TrackingService;

/**
 * Tracks placed orders on checkout_submit_all_after (one page checkout and multishipping)
 *
 * Only orders placed from the quote of the current checkout session are tracked. Orders created via the
 * REST API by other systems (token authentication, no session) are ignored.
 */
class TrackOrderObserver implements ObserverInterface
{
    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var TrackingService
     */
    private TrackingService $trackingService;

    /**
     * @param CheckoutSession $checkoutSession
     * @param TrackingService $trackingService
     */
    public function __construct(CheckoutSession $checkoutSession, TrackingService $trackingService)
    {
        $this->checkoutSession = $checkoutSession;
        $this->trackingService = $trackingService;
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer)
    {
        $sessionQuoteId = (int)$this->checkoutSession->getQuoteId();
        if ($sessionQuoteId === 0) {
            return;
        }

        $event = $observer->getEvent();
        $orders = $event->getData('orders') ?: [$event->getData('order')];

        foreach ($orders as $order) {
            if ($order instanceof Order && (int)$order->getQuoteId() === $sessionQuoteId) {
                $this->trackingService->trackOrder($order);
            }
        }
    }
}
