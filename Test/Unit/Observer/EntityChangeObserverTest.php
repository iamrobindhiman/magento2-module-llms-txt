<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Test\Unit\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RKD\LlmsTxt\Model\Config;
use RKD\LlmsTxt\Model\DirtyFlag;
use RKD\LlmsTxt\Observer\EntityChangeObserver;

class EntityChangeObserverTest extends TestCase
{
    private EntityChangeObserver $observer;
    private DirtyFlag|MockObject $dirtyFlag;
    private Config|MockObject $config;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->dirtyFlag = $this->createMock(DirtyFlag::class);
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->observer = new EntityChangeObserver(
            $this->dirtyFlag,
            $this->config,
            $this->logger
        );
    }

    private function createObserverWithEvent(string $eventName): Observer
    {
        $event = $this->createMock(Event::class);
        $event->method('getName')->willReturn($eventName);

        $observer = $this->createMock(Observer::class);
        $observer->method('getEvent')->willReturn($event);

        return $observer;
    }

    public function testSetsProductsDirtyOnProductSaveAfter(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->dirtyFlag->method('isDirty')->with(DirtyFlag::SECTION_PRODUCTS)->willReturn(false);

        $this->dirtyFlag->expects($this->once())
            ->method('markDirty')
            ->with(DirtyFlag::SECTION_PRODUCTS);

        $this->observer->execute(
            $this->createObserverWithEvent('catalog_product_save_after')
        );
    }

    public function testSetsProductsDirtyOnProductDeleteAfter(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->dirtyFlag->method('isDirty')->with(DirtyFlag::SECTION_PRODUCTS)->willReturn(false);

        $this->dirtyFlag->expects($this->once())
            ->method('markDirty')
            ->with(DirtyFlag::SECTION_PRODUCTS);

        $this->observer->execute(
            $this->createObserverWithEvent('catalog_product_delete_after')
        );
    }

    public function testSetsProductsDirtyOnStockItemSaveAfter(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->dirtyFlag->method('isDirty')->with(DirtyFlag::SECTION_PRODUCTS)->willReturn(false);

        $this->dirtyFlag->expects($this->once())
            ->method('markDirty')
            ->with(DirtyFlag::SECTION_PRODUCTS);

        $this->observer->execute(
            $this->createObserverWithEvent('cataloginventory_stock_item_save_after')
        );
    }

    public function testSetsCmsPagesDirtyOnCmsPageSaveAfter(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->dirtyFlag->method('isDirty')->with(DirtyFlag::SECTION_CMS_PAGES)->willReturn(false);

        $this->dirtyFlag->expects($this->once())
            ->method('markDirty')
            ->with(DirtyFlag::SECTION_CMS_PAGES);

        $this->observer->execute(
            $this->createObserverWithEvent('cms_page_save_after')
        );
    }

    public function testSetsCategoriesDirtyOnCategorySaveAfter(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->dirtyFlag->method('isDirty')->with(DirtyFlag::SECTION_CATEGORIES)->willReturn(false);

        $this->dirtyFlag->expects($this->once())
            ->method('markDirty')
            ->with(DirtyFlag::SECTION_CATEGORIES);

        $this->observer->execute(
            $this->createObserverWithEvent('catalog_category_save_after')
        );
    }

    public function testDoesNothingWhenModuleIsDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);

        $this->dirtyFlag->expects($this->never())->method('isDirty');
        $this->dirtyFlag->expects($this->never())->method('markDirty');

        $this->observer->execute(
            $this->createObserverWithEvent('catalog_product_save_after')
        );
    }

    public function testDoesNothingForUnknownEventName(): void
    {
        $this->config->method('isEnabled')->willReturn(true);

        $this->dirtyFlag->expects($this->never())->method('isDirty');
        $this->dirtyFlag->expects($this->never())->method('markDirty');

        $this->observer->execute(
            $this->createObserverWithEvent('some_unknown_event')
        );
    }

    public function testDoesNotMarkDirtyIfSectionIsAlreadyDirty(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->dirtyFlag->method('isDirty')->with(DirtyFlag::SECTION_PRODUCTS)->willReturn(true);

        $this->dirtyFlag->expects($this->never())->method('markDirty');
        $this->logger->expects($this->never())->method('debug');

        $this->observer->execute(
            $this->createObserverWithEvent('catalog_product_save_after')
        );
    }

    public function testLogsDebugMessageWhenMarkingDirty(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->dirtyFlag->method('isDirty')->willReturn(false);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Marked section "products" dirty'));

        $this->observer->execute(
            $this->createObserverWithEvent('catalog_product_save_after')
        );
    }
}
