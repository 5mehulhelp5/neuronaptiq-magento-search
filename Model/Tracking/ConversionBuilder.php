<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Tracking;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment;
use NeuronaptiQ\Search\Api\TrackingApiClientInterface;

/**
 * Builds the "conversion" object of the tracking request from a quote or an order
 *
 * Product prices and taxes are row totals (price incl. tax for the whole quantity). For configurable
 * products the id and name of the selected variant are used.
 *
 * @phpstan-import-type Conversion from TrackingApiClientInterface
 * @phpstan-import-type ConversionProduct from TrackingApiClientInterface
 */
class ConversionBuilder
{
    /**
     * Builds a cart or checkout conversion from a quote
     *
     * For carts the total is the subtotal incl. tax and shipping is sent empty. For checkouts the total is the
     * grand total and the selected shipping method is sent, if one is selected already. Payment is not known yet
     * and sent empty.
     *
     * @param Quote $quote
     * @param string $type TrackingApiClientInterface::TYPE_CART or TYPE_CHECKOUT
     * @return array
     * @phpstan-return Conversion
     */
    public function fromQuote(Quote $quote, string $type): array
    {
        $products = [];
        $subtotalInclTax = 0.0;
        $itemTaxes = 0.0;

        /** @var AbstractItem $item */
        foreach ($quote->getAllVisibleItems() as $item) {
            $variant = $this->getQuoteVariant($item);
            $rowTotal = (float)$item->getRowTotalInclTax();
            $rowTaxes = (float)$item->getTaxAmount();
            $subtotalInclTax += $rowTotal;
            $itemTaxes += $rowTaxes;

            $products[] = [
                'id' => (string)$variant->getData('product_id'),
                'name' => (string)$variant->getData('name'),
                'count' => (int)$item->getQty(),
                'price' => $rowTotal,
                'taxes' => $rowTaxes,
            ];
        }

        $shipping = ['name' => '', 'price' => 0.0, 'taxes' => 0.0];
        if ($type === TrackingApiClientInterface::TYPE_CHECKOUT) {
            $address = $quote->isVirtual() ? $quote->getBillingAddress() : $quote->getShippingAddress();
            $total = (float)$quote->getGrandTotal();
            $taxes = (float)$address->getTaxAmount();
            if (!$quote->isVirtual() && $address->getShippingMethod()) {
                $shipping = [
                    'name' => (string)$address->getShippingDescription(),
                    'price' => (float)$address->getShippingInclTax(),
                    'taxes' => (float)$address->getShippingTaxAmount(),
                ];
            }
        } else {
            $total = $subtotalInclTax;
            $taxes = $itemTaxes;
        }

        return [
            'type' => $type,
            'channel' => (string)$quote->getStore()->getWebsiteId(),
            // Magento has no cart hash for regular carts, the number stays empty
            'number' => '',
            'currency' => (string)$quote->getQuoteCurrencyCode(),
            'total' => $total,
            'taxes' => $taxes,
            'payment' => ['name' => ''],
            'shipping' => $shipping,
            'products' => $products,
        ];
    }

    /**
     * Builds an order conversion
     *
     * @param Order $order
     * @return array
     * @phpstan-return Conversion
     */
    public function fromOrder(Order $order): array
    {
        $products = [];

        /** @var OrderItem $item */
        foreach ($order->getAllVisibleItems() as $item) {
            $variant = $this->getOrderVariant($item);

            $products[] = [
                'id' => (string)$variant->getProductId(),
                'name' => (string)$variant->getName(),
                'count' => (int)$item->getQtyOrdered(),
                'price' => (float)$item->getRowTotalInclTax(),
                'taxes' => (float)$item->getTaxAmount(),
            ];
        }

        return [
            'type' => TrackingApiClientInterface::TYPE_ORDER,
            'channel' => (string)$order->getStore()->getWebsiteId(),
            'number' => (string)$order->getIncrementId(),
            'currency' => (string)$order->getOrderCurrencyCode(),
            'total' => (float)$order->getGrandTotal(),
            'taxes' => (float)$order->getTaxAmount(),
            'payment' => ['name' => $this->getPaymentName($order)],
            'shipping' => [
                'name' => (string)$order->getShippingDescription(),
                'price' => (float)$order->getShippingInclTax(),
                'taxes' => (float)$order->getShippingTaxAmount(),
            ],
            'products' => $products,
        ];
    }

    /**
     * Returns the variant item of a configurable quote item, otherwise the item itself
     *
     * @param AbstractItem $item
     * @return AbstractItem
     */
    private function getQuoteVariant(AbstractItem $item): AbstractItem
    {
        if ($item->getData('product_type') === Configurable::TYPE_CODE) {
            $children = $item->getChildren();
            if (!empty($children)) {
                return reset($children);
            }
        }

        return $item;
    }

    /**
     * Returns the variant item of a configurable order item, otherwise the item itself
     *
     * @param OrderItem $item
     * @return OrderItem
     */
    private function getOrderVariant(OrderItem $item): OrderItem
    {
        if ($item->getProductType() === Configurable::TYPE_CODE) {
            $children = $item->getChildrenItems();
            if (!empty($children)) {
                return reset($children);
            }
        }

        return $item;
    }

    /**
     * Returns the title of the payment method used for the order
     *
     * @param Order $order
     * @return string
     */
    private function getPaymentName(Order $order): string
    {
        $payment = $order->getPayment();
        if (!$payment instanceof Payment) {
            return $payment ? (string)$payment->getMethod() : '';
        }

        $title = (string)$payment->getAdditionalInformation('method_title');
        if ($title !== '') {
            return $title;
        }

        try {
            return (string)$payment->getMethodInstance()->getTitle();
        } catch (\Throwable $e) {
            return (string)$payment->getMethod();
        }
    }
}
