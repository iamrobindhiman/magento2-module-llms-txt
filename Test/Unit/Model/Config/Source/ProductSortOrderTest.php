<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model\Config\Source;

use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\Config\Source\ProductSortOrder;

class ProductSortOrderTest extends TestCase
{
    public function testToOptionArrayReturnsExpectedOptions(): void
    {
        $source = new ProductSortOrder();
        $options = $source->toOptionArray();

        $this->assertIsArray($options);
        $this->assertCount(5, $options);

        $values = array_column($options, 'value');
        $this->assertContains('bestsellers', $values);
        $this->assertContains('newest', $values);
        $this->assertContains('alphabetical', $values);
        $this->assertContains('price_desc', $values);
        $this->assertContains('price_asc', $values);
    }
}
