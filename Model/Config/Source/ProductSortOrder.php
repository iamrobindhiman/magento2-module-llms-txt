<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProductSortOrder implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'bestsellers', 'label' => __('Best Sellers')],
            ['value' => 'newest', 'label' => __('Newest First')],
            ['value' => 'alphabetical', 'label' => __('Alphabetical (A-Z)')],
            ['value' => 'price_desc', 'label' => __('Price: High to Low')],
            ['value' => 'price_asc', 'label' => __('Price: Low to High')],
        ];
    }
}
