<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Model;

use Magento\Framework\FlagManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RKD\LlmsTxt\Model\DirtyFlag;

class DirtyFlagTest extends TestCase
{
    private DirtyFlag $dirtyFlag;
    private FlagManager|MockObject $flagManager;

    protected function setUp(): void
    {
        $this->flagManager = $this->createMock(FlagManager::class);
        $this->dirtyFlag = new DirtyFlag($this->flagManager);
    }

    public function testSectionProductsConstant(): void
    {
        $this->assertSame('products', DirtyFlag::SECTION_PRODUCTS);
    }

    public function testSectionCmsPagesConstant(): void
    {
        $this->assertSame('cms_pages', DirtyFlag::SECTION_CMS_PAGES);
    }

    public function testSectionCategoriesConstant(): void
    {
        $this->assertSame('categories', DirtyFlag::SECTION_CATEGORIES);
    }

    public function testSectionMetadataConstant(): void
    {
        $this->assertSame('metadata', DirtyFlag::SECTION_METADATA);
    }

    public function testMarkDirtySavesFlagViaFlagManager(): void
    {
        $this->flagManager->expects($this->once())
            ->method('saveFlag')
            ->with('rkd_llmstxt_dirty_products', true);

        $this->dirtyFlag->markDirty(DirtyFlag::SECTION_PRODUCTS);
    }

    public function testIsDirtyReturnsTrueWhenFlagIsSet(): void
    {
        $this->flagManager->method('getFlagData')
            ->with('rkd_llmstxt_dirty_cms_pages')
            ->willReturn(true);

        $this->assertTrue($this->dirtyFlag->isDirty(DirtyFlag::SECTION_CMS_PAGES));
    }

    public function testIsDirtyReturnsFalseWhenFlagIsNotSet(): void
    {
        $this->flagManager->method('getFlagData')
            ->with('rkd_llmstxt_dirty_categories')
            ->willReturn(null);

        $this->assertFalse($this->dirtyFlag->isDirty(DirtyFlag::SECTION_CATEGORIES));
    }

    public function testIsDirtyReturnsFalseWhenFlagIsFalse(): void
    {
        $this->flagManager->method('getFlagData')
            ->with('rkd_llmstxt_dirty_metadata')
            ->willReturn(false);

        $this->assertFalse($this->dirtyFlag->isDirty(DirtyFlag::SECTION_METADATA));
    }

    public function testIsAnyDirtyReturnsTrueWhenOneSectionIsDirty(): void
    {
        $this->flagManager->method('getFlagData')
            ->willReturnCallback(function (string $flag): mixed {
                return $flag === 'rkd_llmstxt_dirty_categories' ? true : null;
            });

        $this->assertTrue($this->dirtyFlag->isAnyDirty());
    }

    public function testIsAnyDirtyReturnsFalseWhenNoSectionsAreDirty(): void
    {
        $this->flagManager->method('getFlagData')
            ->willReturn(null);

        $this->assertFalse($this->dirtyFlag->isAnyDirty());
    }

    public function testIsAnyDirtyReturnsTrueWhenAllSectionsAreDirty(): void
    {
        $this->flagManager->method('getFlagData')
            ->willReturn(true);

        $this->assertTrue($this->dirtyFlag->isAnyDirty());
    }

    public function testClearDirtySavesFalseFlag(): void
    {
        $this->flagManager->expects($this->once())
            ->method('saveFlag')
            ->with('rkd_llmstxt_dirty_products', false);

        $this->dirtyFlag->clearDirty(DirtyFlag::SECTION_PRODUCTS);
    }

    public function testClearAllClearsAllFourSections(): void
    {
        $expectedCalls = [
            ['rkd_llmstxt_dirty_products', false],
            ['rkd_llmstxt_dirty_cms_pages', false],
            ['rkd_llmstxt_dirty_categories', false],
            ['rkd_llmstxt_dirty_metadata', false],
        ];

        $callIndex = 0;
        $this->flagManager->expects($this->exactly(4))
            ->method('saveFlag')
            ->willReturnCallback(function (string $flag, $value) use (&$callIndex, $expectedCalls): void {
                $this->assertSame($expectedCalls[$callIndex][0], $flag);
                $this->assertSame($expectedCalls[$callIndex][1], $value);
                $callIndex++;
            });

        $this->dirtyFlag->clearAll();
    }
}
