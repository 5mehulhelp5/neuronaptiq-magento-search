<?php
declare(strict_types=1);

namespace NeuronaptiQ\Search\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use NeuronaptiQ\Search\Model\Config;

/**
 * Options provider for environment selection
 */
class Environment implements OptionSourceInterface
{
    /**
     * @inheritDoc
     *
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::ENV_DEV,
                'label' => __('Development (dev)')
            ],
            [
                'value' => Config::ENV_TEST,
                'label' => __('Testing (test)')
            ],
            [
                'value' => Config::ENV_PROD,
                'label' => __('Production (prod)')
            ]
        ];
    }
}
