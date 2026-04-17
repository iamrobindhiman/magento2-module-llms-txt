<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use RKD\LlmsTxt\Model\Config;
use RKD\LlmsTxt\Model\DirtyFlag;

/**
 * Single observer handling all entity change events
 *
 * Maps Magento events to section dirty flags:
 * - catalog_product_save_after → products
 * - cataloginventory_stock_item_save_after → products
 * - cms_page_save_after → cms_pages
 * - catalog_category_save_after → categories
 *
 * Does NOT trigger generation — only sets the dirty flag.
 * The cron job checks flags and calls Generator::generate() if needed.
 */
class EntityChangeObserver implements ObserverInterface
{
    /**
     * Map event names to section dirty flags
     */
    private const EVENT_SECTION_MAP = [
        'catalog_product_save_after' => DirtyFlag::SECTION_PRODUCTS,
        'catalog_product_delete_after' => DirtyFlag::SECTION_PRODUCTS,
        'cataloginventory_stock_item_save_after' => DirtyFlag::SECTION_PRODUCTS,
        'cms_page_save_after' => DirtyFlag::SECTION_CMS_PAGES,
        'cms_page_delete_after' => DirtyFlag::SECTION_CMS_PAGES,
        'catalog_category_save_after' => DirtyFlag::SECTION_CATEGORIES,
        'catalog_category_delete_after' => DirtyFlag::SECTION_CATEGORIES,
    ];

    public function __construct(
        private readonly DirtyFlag $dirtyFlag,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $eventName = $observer->getEvent()->getName();

        if (!$this->config->isEnabled()) {
            return;
        }

        $section = self::EVENT_SECTION_MAP[$eventName] ?? null;

        if ($section === null) {
            return;
        }

        // Only set flag if not already dirty (avoids unnecessary DB writes during bulk operations)
        if (!$this->dirtyFlag->isDirty($section)) {
            $this->dirtyFlag->markDirty($section);

            $this->logger->debug(sprintf(
                'RKD LLMs.txt: Marked section "%s" dirty (event: %s)',
                $section,
                $eventName
            ));
        }
    }
}
