<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Search;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Ranges of the price facet according to the layered navigation settings of the shop (SimpKo/project#324, #325)
 *
 * - Manual: fixed step (price_range_step), the ranges beyond price_range_max_intervals are merged into the last one
 * - Automatic (equalize price ranges): the search service calculates the step like Magento, no parameters
 * - Improved (equalize product counts): not supported by the search service yet, the automatic step is used
 *
 * @phpstan-import-type PriceRangesParam from SearchFilters
 */
class PriceRangesResolver
{
    public const XML_PATH_RANGE_CALCULATION = 'catalog/layered_navigation/price_range_calculation';
    public const XML_PATH_RANGE_STEP = 'catalog/layered_navigation/price_range_step';
    public const XML_PATH_RANGE_MAX_INTERVALS = 'catalog/layered_navigation/price_range_max_intervals';

    public const CALCULATION_MANUAL = 'manual';

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Returns the priceRanges parameter of the search request
     *
     * @param string|null $calculation Calculation of the request (e.g. price_dynamic_algorithm), null: the setting
     * @return array [] for the automatic step, or step and maxIntervals
     * @phpstan-return PriceRangesParam
     */
    public function resolve(?string $calculation = null): array
    {
        $calculation = $calculation ?? (string)$this->scopeConfig->getValue(
            self::XML_PATH_RANGE_CALCULATION,
            ScopeInterface::SCOPE_STORE
        );
        if ($calculation !== self::CALCULATION_MANUAL) {
            return [];
        }

        $step = (float)$this->scopeConfig->getValue(self::XML_PATH_RANGE_STEP, ScopeInterface::SCOPE_STORE);
        if ($step <= 0) {
            return [];
        }

        $ranges = ['step' => $step];
        $maxIntervals = (int)$this->scopeConfig->getValue(
            self::XML_PATH_RANGE_MAX_INTERVALS,
            ScopeInterface::SCOPE_STORE
        );
        if ($maxIntervals > 0) {
            $ranges['maxIntervals'] = $maxIntervals;
        }

        return $ranges;
    }
}
