<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Plugin;

use Magento\Search\Model\Autocomplete\ItemInterface;
use Magento\Search\Model\AutocompleteInterface;
use NeuronaptiQ\Search\Model\Autocomplete\ItemConverter;
use NeuronaptiQ\Search\Model\Autocomplete\SuggestState;
use NeuronaptiQ\Search\Model\AutocompleteService;
use NeuronaptiQ\Search\Model\Config;
use NeuronaptiQ\Search\Model\PageCacheGuard;
use Psr\Log\LoggerInterface;

/**
 * Intercepts Magento Autocomplete to replace the product suggestions with NeuronaptiQ search results
 *
 * Typed suggestions of other data providers (e.g. categories and terms of Smile ElasticSuite) are kept, except
 * for the types delivered by NeuronaptiQ: products always, categories if NeuronaptiQ returns category suggestions.
 * Magento's default search term suggestions have no type and are not kept. The replaced types are published in
 * SuggestState while the data providers run, so integrations can skip creating suggestions that are dropped anyway.
 *
 * On API errors and empty results the Magento suggestions are returned unchanged and the response is kept out of
 * the full page cache.
 */
class AutocompletePlugin
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var AutocompleteService
     */
    private AutocompleteService $autocompleteService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var PageCacheGuard
     */
    private PageCacheGuard $pageCacheGuard;

    /**
     * @var SuggestState
     */
    private SuggestState $suggestState;

    /**
     * @param Config $config
     * @param AutocompleteService $autocompleteService
     * @param LoggerInterface $logger
     * @param PageCacheGuard $pageCacheGuard
     * @param SuggestState $suggestState
     */
    public function __construct(
        Config $config,
        AutocompleteService $autocompleteService,
        LoggerInterface $logger,
        PageCacheGuard $pageCacheGuard,
        SuggestState $suggestState
    ) {
        $this->config = $config;
        $this->autocompleteService = $autocompleteService;
        $this->logger = $logger;
        $this->pageCacheGuard = $pageCacheGuard;
        $this->suggestState = $suggestState;
    }

    /**
     * Around plugin on getItems to replace the product suggestions when the module is enabled
     *
     * @param AutocompleteInterface $subject
     * @param callable $proceed
     * @return ItemInterface[]
     */
    public function aroundGetItems(AutocompleteInterface $subject, callable $proceed): array
    {
        if (!$this->config->isSearchEnabled()) {
            return $proceed();
        }

        try {
            $ownItems = $this->autocompleteService->getSuggestItems();
        } catch (\Throwable $e) {
            $this->logger->error(
                '[NeuronaptiQ_Search] AutocompletePlugin error: ' . $e->getMessage(),
                ['exception' => $e]
            );
            // Graceful fallback to default Magento behavior on unexpected failure
            return $this->fallback($proceed);
        }

        if (empty($ownItems)) {
            // Empty results (e.g. for requests classified as bots) are not shown and must not be cached
            $this->logger->debug('[NeuronaptiQ_Search] No suggestions, falling back to the Magento suggestions');
            return $this->fallback($proceed);
        }

        // Own suggestions first (products, categories), followed by the other typed suggestions; integrations skip
        // the suggestions of the own types while the data providers run
        $ownTypes = $this->getTypes($ownItems);
        $this->suggestState->setReplacedTypes($ownTypes);
        try {
            $items = $proceed();
        } finally {
            $this->suggestState->setReplacedTypes([]);
        }

        return array_merge($ownItems, $this->getAdditionalItems($items, $ownTypes));
    }

    /**
     * Returns the suggestions of the Magento search and keeps the response out of the full page cache
     *
     * @param callable $proceed
     * @return ItemInterface[]
     */
    private function fallback(callable $proceed): array
    {
        $this->pageCacheGuard->disableCaching();
        return $proceed();
    }

    /**
     * Returns the types of the given suggestions, products are always delivered by NeuronaptiQ
     *
     * @param ItemInterface[] $items
     * @return string[]
     */
    private function getTypes(array $items): array
    {
        $types = [ItemConverter::TYPE_PRODUCT => ItemConverter::TYPE_PRODUCT];
        foreach ($items as $item) {
            $type = $this->getType($item);
            if ($type !== '') {
                $types[$type] = $type;
            }
        }

        return array_values($types);
    }

    /**
     * Returns the type of a suggestion, or '' for untyped suggestions
     *
     * @param ItemInterface $item
     * @return string
     */
    private function getType(ItemInterface $item): string
    {
        $type = $item->toArray()['type'] ?? null;
        return is_string($type) ? $type : '';
    }

    /**
     * Returns the suggestions of other data providers that are kept: typed items of other types
     *
     * @param mixed $items
     * @param string[] $ownTypes
     * @return ItemInterface[]
     */
    private function getAdditionalItems($items, array $ownTypes): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, function ($item) use ($ownTypes): bool {
            if (!$item instanceof ItemInterface) {
                return false;
            }
            $type = $this->getType($item);
            return $type !== '' && !in_array($type, $ownTypes, true);
        }));
    }
}
