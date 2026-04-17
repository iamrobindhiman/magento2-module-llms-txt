<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\Config\Source\CronSchedule;

class CronScheduleTest extends TestCase
{
    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $source = new CronSchedule();
        $options = $source->toOptionArray();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);

        $values = array_column($options, 'value');
        $this->assertContains('0 * * * *', $values);   // Hourly
        $this->assertContains('0 0 * * *', $values);   // Daily
        $this->assertContains('0 0 * * 0', $values);   // Weekly
    }

    public function testEachOptionHasValueAndLabel(): void
    {
        $source = new CronSchedule();
        foreach ($source->toOptionArray() as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertNotEmpty($option['value']);
        }
    }
}
