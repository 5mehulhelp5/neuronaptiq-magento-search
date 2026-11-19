<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Observer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\AbstractAction;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use NeuronaptiQ\Search\Model\TrackingService;

/**
 * Tracks visits of the checkout page on controller_action_postdispatch_checkout_index_index
 */
class TrackCheckoutObserver implements ObserverInterface
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
        // The checkout redirects e.g. to the cart if it is empty, those requests are not tracked
        $action = $observer->getEvent()->getData('controller_action');
        if ($action instanceof AbstractAction) {
            $response = $action->getResponse();
            if ($response instanceof HttpResponse && $response->isRedirect()) {
                return;
            }
        }

        $quote = $this->checkoutSession->getQuote();
        if ((int)$quote->getItemsCount() > 0) {
            $this->trackingService->trackCheckout($quote);
        }
    }
}
