<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\Config\Source\ContentDepth;

class ContentDepthTest extends TestCase
{
    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $source = new ContentDepth();
        $options = $source->toOptionArray();

        $this->assertIsArray($options);
        $this->assertCount(3, $options);

        $values = array_column($options, 'value');
        $this->assertContains('summary', $values);
        $this->assertContains('detailed', $values);
        $this->assertContains('complete', $values);
    }
}
