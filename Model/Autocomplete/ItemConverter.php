<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Autocomplete;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Search\Model\Autocomplete\ItemFactory;
use Magento\Search\Model\Autocomplete\ItemInterface;
use NeuronaptiQ\Search\Model\Config;

/**
 * Converts loaded products into Magento Autocomplete ItemInterface instances
 *
 * With enhanced suggestions enabled, the items get a product image, the price and the product URL (used by the
 * form-mini mixin to open the product page directly). Otherwise only the title is provided and Magento's default
 * behavior applies (the suggestion text is searched).
 *
 * The items use the same fields as the product suggestions of Smile ElasticSuite (type, title, url, image, price),
 * so themes that render ElasticSuite suggestions by type (e.g. Hyvä with the Smile ElasticSuite compatibility
 * module) display them as products. Like there, price is HTML (the default price is escaped text).
 *
 * With "Suggest Parent Products Instead of Variants" enabled, a variant is shown as its parent product (name, image
 * and price of the parent); the URL keeps the variant preselection. Products whose price is the lowest price of
 * their children (configurable, grouped, bundle) get the price prefixed with "As low as".
 *
 * Before an item is created, the event neuronaptiq_search_autocomplete_product_item is dispatched with the
 * displayed product, its parent (variants shown as themselves), the found variant (variants shown as their parent),
 * the enhanced flag and the item data as DataObject (item_data); observers can change or add any field, e.g.
 * replace the price HTML. Observers have to escape the values they set in price.
 *
 * @phpstan-import-type ProductEntry from \NeuronaptiQ\Search\Model\ProductLoader
 */
class ItemConverter
{
    public const ROW_CLASS = 'neuronaptiq-suggest-item';
    public const ROW_CLASS_ENHANCED = 'neuronaptiq-suggest-item--enhanced';
    public const TYPE_PRODUCT = 'product';
    public const EVENT_PRODUCT_ITEM = 'neuronaptiq_search_autocomplete_product_item';

    private const IMAGE_ID = 'product_thumbnail_image';
    private const IMAGE_SIZE = 100;

    /**
     * Product types whose final price is the lowest price of their children
     */
    private const PRICE_FROM_TYPES = ['configurable', 'grouped', 'bundle'];

    /**
     * @var ItemFactory
     */
    private ItemFactory $itemFactory;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ImageHelper
     */
    private ImageHelper $imageHelper;

    /**
     * @var PriceCurrencyInterface
     */
    private PriceCurrencyInterface $priceCurrency;

    /**
     * @var EventManagerInterface
     */
    private EventManagerInterface $eventManager;

    /**
     * @var Escaper
     */
    private Escaper $escaper;

    /**
     * @param ItemFactory $itemFactory
     * @param Config $config
     * @param ImageHelper $imageHelper
     * @param PriceCurrencyInterface $priceCurrency
     * @param EventManagerInterface $eventManager
     * @param Escaper $escaper
     */
    public function __construct(
        ItemFactory $itemFactory,
        Config $config,
        ImageHelper $imageHelper,
        PriceCurrencyInterface $priceCurrency,
        EventManagerInterface $eventManager,
        Escaper $escaper
    ) {
        $this->itemFactory = $itemFactory;
        $this->config = $config;
        $this->imageHelper = $imageHelper;
        $this->priceCurrency = $priceCurrency;
        $this->eventManager = $eventManager;
        $this->escaper = $escaper;
    }

    /**
     * Convert list of loaded products with scores into autocomplete items
     *
     * @param array $productEntries List of entries with 'product' (Product), 'score' (float) and 'parent'
     * @return ItemInterface[]
     * @phpstan-param list<ProductEntry> $productEntries
     */
    public function convert(array $productEntries): array
    {
        $enhanced = $this->config->isEnhancedSuggestionsEnabled();
        $suggestParents = $this->config->isSuggestParentProductsEnabled();
        $items = [];

        foreach ($productEntries as $entry) {
            $product = $entry['product'];
            $parent = $entry['parent'];
            $variant = null;
            if ($suggestParents && $parent !== null) {
                $variant = $product;
                $product = $parent;
                $parent = null;
            }

            $itemData = [
                'type' => self::TYPE_PRODUCT,
                'title' => (string)$product->getName(),
                // Rendered in <span class="amount"> by the suggest template, kept empty
                'num_results' => '',
                'id' => (int)$product->getId(),
                'sku' => (string)$product->getSku(),
                'type_id' => (string)$product->getTypeId(),
                'is_saleable' => (bool)$product->isSaleable(),
                'score' => (float)$entry['score'],
                'row_class' => self::ROW_CLASS,
            ];

            if ($enhanced) {
                $itemData['row_class'] .= ' ' . self::ROW_CLASS_ENHANCED;
                // The target URL of the variant is the parent URL with the variant preselection
                $urlSource = $variant ?? $product;
                $itemData['url'] = (string)($urlSource->getData('target_url') ?: $product->getProductUrl());
                $itemData['image'] = $this->getImageUrl($product, $parent ?? $variant);
                $itemData['price'] = $this->escaper->escapeHtml($this->getFormattedPrice($product));
            }

            // Observers can change the item data, e.g. the price HTML or additional attributes
            $transport = new DataObject($itemData);
            $this->eventManager->dispatch(self::EVENT_PRODUCT_ITEM, [
                'product' => $product,
                'parent' => $parent,
                'variant' => $variant,
                'enhanced' => $enhanced,
                'item_data' => $transport,
            ]);

            $items[] = $this->itemFactory->create($transport->getData());
        }

        return $items;
    }

    /**
     * Returns the thumbnail URL of the product, falls back to the related product and then to the placeholder
     *
     * The related product is the parent of a variant, or the found variant of a parent shown instead of it.
     *
     * @param Product $product
     * @param Product|null $related
     * @return string
     */
    private function getImageUrl(Product $product, ?Product $related): string
    {
        $source = $product;
        if (!$this->hasThumbnail($product) && $related !== null && $this->hasThumbnail($related)) {
            $source = $related;
        }

        return (string)$this->imageHelper->init($source, self::IMAGE_ID)
            ->resize(self::IMAGE_SIZE, self::IMAGE_SIZE)
            ->getUrl();
    }

    /**
     * Checks whether the product has its own thumbnail
     *
     * @param Product $product
     * @return bool
     */
    private function hasThumbnail(Product $product): bool
    {
        $thumbnail = (string)$product->getData('thumbnail');
        return $thumbnail !== '' && $thumbnail !== 'no_selection';
    }

    /**
     * Returns the formatted final price, "As low as" for products priced by their children
     *
     * The final price of configurable, grouped and bundle products is the lowest price of their children. The price
     * info amount is already converted to the current currency and contains the tax adjustment according to the
     * tax display settings, so it is only formatted.
     *
     * @param Product $product
     * @return string
     */
    private function getFormattedPrice(Product $product): string
    {
        $amount = $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE)->getAmount()->getValue();
        $price = $this->priceCurrency->format($amount, false);
        if (in_array((string)$product->getTypeId(), self::PRICE_FROM_TYPES, true)) {
            return (string)__('As low as %1', $price);
        }

        return $price;
    }
}
