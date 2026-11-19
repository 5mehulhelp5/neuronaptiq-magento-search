<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Plugin;

use Magento\Checkout\Api\Data\PaymentDetailsInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use NeuronaptiQ\Search\Model\TrackingService;
use Psr\Log\LoggerInterface;

/**
 * Tracks a checkout update with the selected shipping method when the customer proceeds to "Review & Payments"
 *
 * Only the quote of the current checkout session is tracked, calls of other systems via the REST API are ignored.
 */
class TrackShippingInformationPlugin
{
    /**
     * @var CartRepositoryInterface
     */
    private CartRepositoryInterface $cartRepository;

    /**
     * @var CheckoutSession
     */
    private CheckoutSession $checkoutSession;

    /**
     * @var TrackingService
     */
    private TrackingService $trackingService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param CheckoutSession $checkoutSession
     * @param TrackingService $trackingService
     * @param LoggerInterface $logger
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        CheckoutSession $checkoutSession,
        TrackingService $trackingService,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->checkoutSession = $checkoutSession;
        $this->trackingService = $trackingService;
        $this->logger = $logger;
    }

    /**
     * Tracks the checkout after the shipping information has been saved
     *
     * @param ShippingInformationManagementInterface $subject
     * @param PaymentDetailsInterface $result
     * @param int|string $cartId
     * @param ShippingInformationInterface $addressInformation
     * @return PaymentDetailsInterface
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterSaveAddressInformation(
        ShippingInformationManagementInterface $subject,
        PaymentDetailsInterface $result,
        $cartId,
        ShippingInformationInterface $addressInformation
    ): PaymentDetailsInterface {
        $sessionQuoteId = (int)$this->checkoutSession->getQuoteId();
        if ($sessionQuoteId === 0 || $sessionQuoteId !== (int)$cartId) {
            return $result;
        }

        try {
            $quote = $this->cartRepository->getActive((int)$cartId);
            if ($quote instanceof Quote) {
                $this->trackingService->trackCheckout($quote);
            }
        } catch (\Throwable $e) {
            // Never break the checkout because of tracking
            $this->logger->error(
                sprintf('[NeuronaptiQ_Search] Tracking of shipping information failed: %s', $e->getMessage()),
                ['exception' => $e]
            );
        }

        return $result;
    }
}
