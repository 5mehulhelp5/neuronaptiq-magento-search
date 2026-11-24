<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Autocomplete;

/**
 * Types of the suggestions NeuronaptiQ delivers in the current autocomplete request (e.g. product, category)
 *
 * Set by the autocomplete plugin while the other data providers create their suggestions. Integrations (e.g. Smile
 * ElasticSuite) skip the suggestions of these types, they are replaced anyway. Empty outside of the autocomplete and
 * when Magento's suggestions are used (module disabled, API errors, no NeuronaptiQ suggestions).
 */
class SuggestState
{
    /**
     * @var string[]
     */
    private array $replacedTypes = [];

    /**
     * Sets the types of the suggestions NeuronaptiQ delivers, an empty list after the data providers
     *
     * @param string[] $types
     * @return void
     */
    public function setReplacedTypes(array $types): void
    {
        $this->replacedTypes = array_values($types);
    }

    /**
     * Whether the suggestions of the given type are replaced by NeuronaptiQ suggestions
     *
     * @param string $type
     * @return bool
     */
    public function isReplaced(string $type): bool
    {
        return in_array($type, $this->replacedTypes, true);
    }
}
