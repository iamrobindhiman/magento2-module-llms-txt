<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ContentDepth implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'summary', 'label' => __('Summary (name + short description)')],
            ['value' => 'detailed', 'label' => __('Detailed (+ attributes, price, SKU)')],
            ['value' => 'complete', 'label' => __('Complete (+ full description)')],
        ];
    }
}
