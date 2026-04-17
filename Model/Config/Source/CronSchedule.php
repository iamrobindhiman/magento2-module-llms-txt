<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CronSchedule implements OptionSourceInterface
{
    /**
     * Values are real cron expressions that Magento's cron scheduler can use directly.
     *
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '0 * * * *', 'label' => __('Every Hour')],
            ['value' => '0 0 * * *', 'label' => __('Daily (at midnight)')],
            ['value' => '0 0 * * 0', 'label' => __('Weekly (Sunday midnight)')],
        ];
    }
}
