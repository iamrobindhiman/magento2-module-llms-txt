<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\Config;

class ConfigTest extends TestCase
{
    private Config $config;
    private ScopeConfigInterface|MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->config = new Config($this->scopeConfig);
    }

    public function testIsEnabledReturnsTrueWhenConfigSet(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('rkd_llmstxt/general/enabled', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn(true);

        $this->assertTrue($this->config->isEnabled(1));
    }

    public function testIsEnabledReturnsFalseWhenConfigNotSet(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('rkd_llmstxt/general/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->config->isEnabled());
    }

    public function testGetProductLimitReturnsConfiguredValue(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('rkd_llmstxt/content/product_limit', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('5000');

        $this->assertSame(5000, $this->config->getProductLimit(1));
    }

    public function testGetProductLimitReturnsZeroWhenNull(): void
    {
        $this->scopeConfig->method('getValue')
            ->willReturn(null);

        $this->assertSame(0, $this->config->getProductLimit());
    }

    public function testGetCategoryDepthLimitReturnsInt(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('rkd_llmstxt/content/category_depth_limit', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('3');

        $this->assertSame(3, $this->config->getCategoryDepthLimit());
    }

    public function testIsExcludeOutOfStockReturnsBoolean(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('rkd_llmstxt/content/exclude_out_of_stock', ScopeInterface::SCOPE_STORE, 2)
            ->willReturn(true);

        $this->assertTrue($this->config->isExcludeOutOfStock(2));
    }

    public function testIsFullTxtEnabledReturnsFalse(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with('rkd_llmstxt/full_txt/enabled', ScopeInterface::SCOPE_STORE, null)
            ->willReturn(false);

        $this->assertFalse($this->config->isFullTxtEnabled());
    }

    public function testGetContentDepthReturnsString(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('rkd_llmstxt/full_txt/content_depth', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('detailed');

        $this->assertSame('detailed', $this->config->getContentDepth());
    }

    public function testGetProductSortOrderReturnsDefault(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('rkd_llmstxt/content/product_sort_order', ScopeInterface::SCOPE_STORE, null)
            ->willReturn('bestsellers');

        $this->assertSame('bestsellers', $this->config->getProductSortOrder());
    }

    public function testBooleanMethodsReturnCorrectTypes(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->willReturn(true);

        $this->assertIsBool($this->config->isIncludePrice());
        $this->assertIsBool($this->config->isIncludeSku());
        $this->assertIsBool($this->config->isIncludeShortDescription());
        $this->assertIsBool($this->config->isValidateOnGeneration());
        $this->assertIsBool($this->config->isBlockOnErrors());
        $this->assertIsBool($this->config->isRobotsAutoInject());
        $this->assertIsBool($this->config->isAutoRegenerationEnabled());
        $this->assertIsBool($this->config->isIncludeCmsPages());
        $this->assertIsBool($this->config->isIncludeCategories());
        $this->assertIsBool($this->config->isIncludeProducts());
        $this->assertIsBool($this->config->isIncludeMetadata());
    }
}
