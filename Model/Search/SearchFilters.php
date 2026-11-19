<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Search;

/**
 * Selection of the layered navigation and the facets requested from the search API (SimpKo/project#324)
 *
 * - groups: selected values per group, the group is the attribute code, the values are option ids (OR within a
 *   group, AND between the groups)
 * - categoryIds: hard category filter, the context of the search (e.g. a category selected in the layered navigation)
 * - price: price filter in the base currency, maxExclusive excludes the maximum (price < max)
 * - facets: compute the facets, priceRanges: the ranges of the price facet ([] = automatic step, or step and
 *   maxIntervals like Magento "Manual")
 *
 * Immutable, the with* methods return a copy.
 *
 * @phpstan-type PriceRangesParam array{step?: float, maxIntervals?: int}
 */
class SearchFilters
{
    /**
     * @var array<string, list<string>>
     */
    private array $groups = [];

    /**
     * @var list<string>
     */
    private array $categoryIds = [];

    /**
     * @var float|null
     */
    private ?float $priceMin = null;

    /**
     * @var float|null
     */
    private ?float $priceMax = null;

    /**
     * @var bool
     */
    private bool $priceMaxExclusive = false;

    /**
     * @var bool
     */
    private bool $facets = false;

    /**
     * @var array|null
     * @phpstan-var PriceRangesParam|null
     */
    private ?array $priceRanges = null;

    /**
     * Adds selected values of a group, empty groups or values are ignored
     *
     * @param string $group
     * @param array $values
     * @return self
     * @phpstan-param array<mixed> $values
     */
    public function withGroup(string $group, array $values): self
    {
        $group = trim($group);
        $clean = [];
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string)$value) !== '') {
                $clean[] = trim((string)$value);
            }
        }

        $copy = clone $this;
        if ($group !== '' && $clean !== []) {
            $copy->groups[$group] = array_values(array_unique(array_merge($copy->groups[$group] ?? [], $clean)));
        }

        return $copy;
    }

    /**
     * Adds categories to the hard category filter
     *
     * @param array $categoryIds
     * @return self
     * @phpstan-param array<mixed> $categoryIds
     */
    public function withCategoryIds(array $categoryIds): self
    {
        $copy = clone $this;
        foreach ($categoryIds as $categoryId) {
            if (is_scalar($categoryId) && (int)$categoryId > 0) {
                $copy->categoryIds[] = (string)(int)$categoryId;
            }
        }
        $copy->categoryIds = array_values(array_unique($copy->categoryIds));

        return $copy;
    }

    /**
     * Sets the price filter, null is no limit
     *
     * @param float|null $min
     * @param float|null $max
     * @param bool $maxExclusive
     * @return self
     */
    public function withPrice(?float $min, ?float $max, bool $maxExclusive = false): self
    {
        $copy = clone $this;
        $copy->priceMin = $min;
        $copy->priceMax = $max;
        $copy->priceMaxExclusive = $max !== null && $maxExclusive;

        return $copy;
    }

    /**
     * Requests the facets with the given ranges of the price facet
     *
     * @param array $priceRanges [] for the automatic step, or step and maxIntervals
     * @return self
     * @phpstan-param PriceRangesParam $priceRanges
     */
    public function withFacets(array $priceRanges = []): self
    {
        $copy = clone $this;
        $copy->facets = true;
        $copy->priceRanges = $priceRanges;

        return $copy;
    }

    /**
     * Selected values per group (attribute code)
     *
     * @return array<string, list<string>>
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Categories of the hard category filter
     *
     * @return list<string>
     */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    /**
     * Minimum of the price filter, null is no limit
     *
     * @return float|null
     */
    public function getPriceMin(): ?float
    {
        return $this->priceMin;
    }

    /**
     * Maximum of the price filter, null is no limit
     *
     * @return float|null
     */
    public function getPriceMax(): ?float
    {
        return $this->priceMax;
    }

    /**
     * Whether the maximum is excluded (price < max)
     *
     * @return bool
     */
    public function isPriceMaxExclusive(): bool
    {
        return $this->priceMaxExclusive;
    }

    /**
     * Whether the facets are requested
     *
     * @return bool
     */
    public function hasFacets(): bool
    {
        return $this->facets;
    }

    /**
     * Ranges of the price facet, null without facets
     *
     * @return array|null
     * @phpstan-return PriceRangesParam|null
     */
    public function getPriceRanges(): ?array
    {
        return $this->priceRanges;
    }

    /**
     * Whether a filter is selected (groups, categories or price)
     *
     * @return bool
     */
    public function hasSelection(): bool
    {
        return $this->groups !== [] || $this->categoryIds !== [] || $this->priceMin !== null
            || $this->priceMax !== null;
    }

    /**
     * Stable representation, e.g. for cache keys
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $groups = $this->groups;
        ksort($groups);
        foreach ($groups as &$values) {
            sort($values);
        }
        unset($values);
        $categoryIds = $this->categoryIds;
        sort($categoryIds);

        return [
            'groups' => $groups,
            'categoryIds' => $categoryIds,
            'price' => [$this->priceMin, $this->priceMax, $this->priceMaxExclusive],
            'facets' => $this->facets,
            'priceRanges' => $this->priceRanges,
        ];
    }
}
