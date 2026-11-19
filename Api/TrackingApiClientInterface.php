<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Api;

use NeuronaptiQ\Search\Exception\SearchApiException;

/**
 * Interface for the tracking endpoint of the NeuronaptiQ REST API
 *
 * @phpstan-type ConversionProduct array{id: string, name: string, count: int, price: float, taxes: float}
 * @phpstan-type Conversion array{
 *     type: string,
 *     channel: string,
 *     number?: string,
 *     currency: string,
 *     total: float,
 *     taxes: float,
 *     payment: array{name: string},
 *     shipping: array{name: string, price: float, taxes: float},
 *     products: list<ConversionProduct>
 * }
 */
interface TrackingApiClientInterface
{
    public const TYPE_CART = 'cart';
    public const TYPE_CHECKOUT = 'checkout';
    public const TYPE_ORDER = 'order';

    /**
     * Sends a conversion event (cart, checkout or order) to the tracking endpoint
     *
     * @param array $conversion
     * @param int|null $storeId
     * @return void
     * @phpstan-param Conversion $conversion
     * @throws SearchApiException If endpoint/token are not configured, the API is unreachable or responds non-2xx
     */
    public function track(array $conversion, ?int $storeId = null): void;

    /**
     * Sends the data of the logged-in customer to the tracking endpoint, without conversion
     *
     * Returns false without sending if no customer data is available (guest or sending customer data disabled).
     *
     * @param int|null $storeId
     * @return bool
     * @throws SearchApiException If endpoint/token are not configured, the API is unreachable or responds non-2xx
     */
    public function trackCustomer(?int $storeId = null): bool;
}
