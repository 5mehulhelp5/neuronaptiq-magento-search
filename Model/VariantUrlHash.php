<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model;

/**
 * Builds the URL hash that preselects a variant on the product page (#attributeId=optionId&...)
 *
 * The hash is evaluated by the swatch renderer / configurable options of the product page.
 */
class VariantUrlHash
{
    /**
     * Builds the hash from the options of an API hit, returns an empty string if there are no options
     *
     * @param array $options API options, each with 'id' (option id) and 'group' => ['id' => attribute id]
     * @return string Hash including the leading '#', or ''
     * @phpstan-param array<mixed> $options
     */
    public function build(array $options): string
    {
        $params = [];
        foreach ($options as $option) {
            $attributeId = is_array($option) ? ($option['group']['id'] ?? null) : null;
            $optionId = is_array($option) ? ($option['id'] ?? null) : null;
            if (is_scalar($attributeId) && is_scalar($optionId)) {
                $params[] = rawurlencode((string)$attributeId) . '=' . rawurlencode((string)$optionId);
            }
        }

        return $params ? '#' . implode('&', $params) : '';
    }

    /**
     * Appends the hash to the URL, an existing hash is replaced
     *
     * @param string $url
     * @param string $hash
     * @return string
     */
    public function append(string $url, string $hash): string
    {
        if ($hash === '') {
            return $url;
        }

        $position = strpos($url, '#');
        return ($position === false ? $url : substr($url, 0, $position)) . $hash;
    }
}
