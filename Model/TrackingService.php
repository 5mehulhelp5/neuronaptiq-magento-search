<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use NeuronaptiQ\Search\Api\TrackingApiClientInterface;
use NeuronaptiQ\Search\Model\Tracking\ConversionBuilder;
use Psr\Log\LoggerInterface;

/**
 * Tracks cart changes, checkout visits, orders and customer logins via the NeuronaptiQ API
 *
 * Tracking runs synchronously, errors are only logged so they never affect the cart or checkout.
 */
class TrackingService
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ConversionBuilder
     */
    private ConversionBuilder $conversionBuilder;

    /**
     * @var TrackingApiClientInterface
     */
    private TrackingApiClientInterface $trackingApiClient;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Config $config
     * @param ConversionBuilder $conversionBuilder
     * @param TrackingApiClientInterface $trackingApiClient
     * @param LoggerInterface $logger
     */
    public function __construct(
        Config $config,
        ConversionBuilder $conversionBuilder,
        TrackingApiClientInterface $trackingApiClient,
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->conversionBuilder = $conversionBuilder;
        $this->trackingApiClient = $trackingApiClient;
        $this->logger = $logger;
    }

    /**
     * Tracks a cart change
     *
     * @param Quote $quote
     * @return void
     */
    public function trackCart(Quote $quote): void
    {
        $this->trackQuote($quote, TrackingApiClientInterface::TYPE_CART);
    }

    /**
     * Tracks a checkout visit
     *
     * @param Quote $quote
     * @return void
     */
    public function trackCheckout(Quote $quote): void
    {
        $this->trackQuote($quote, TrackingApiClientInterface::TYPE_CHECKOUT);
    }

    /**
     * Tracks a customer login, sends the customer data without conversion
     *
     * Requires tracking and sending customer data to be enabled.
     *
     * @param int $storeId
     * @return void
     */
    public function trackLogin(int $storeId): void
    {
        if (!$this->config->isTrackingEnabled($storeId) || !$this->config->isCustomerDataEnabled($storeId)) {
            return;
        }

        try {
            $this->trackingApiClient->trackCustomer($storeId);
        } catch (\Throwable $e) {
            $this->logError('login', $e);
        }
    }

    /**
     * Tracks a placed order
     *
     * @param Order $order
     * @return void
     */
    public function trackOrder(Order $order): void
    {
        $storeId = (int)$order->getStoreId();
        if (!$this->config->isTrackingEnabled($storeId)) {
            return;
        }

        try {
            $this->trackingApiClient->track($this->conversionBuilder->fromOrder($order), $storeId);
        } catch (\Throwable $e) {
            $this->logError(TrackingApiClientInterface::TYPE_ORDER, $e);
        }
    }

    /**
     * Tracks a cart or checkout conversion of the quote
     *
     * @param Quote $quote
     * @param string $type
     * @return void
     */
    private function trackQuote(Quote $quote, string $type): void
    {
        $storeId = (int)$quote->getStoreId();
        if (!$this->config->isTrackingEnabled($storeId)) {
            return;
        }

        try {
            $this->trackingApiClient->track($this->conversionBuilder->fromQuote($quote, $type), $storeId);
        } catch (\Throwable $e) {
            $this->logError($type, $e);
        }
    }

    /**
     * Logs a tracking error
     *
     * @param string $type
     * @param \Throwable $e
     * @return void
     */
    private function logError(string $type, \Throwable $e): void
    {
        $this->logger->error(
            sprintf('[NeuronaptiQ_Search] Tracking of "%s" failed: %s', $type, $e->getMessage()),
            ['exception' => $e]
        );
    }
}
