<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model;

use Magento\Framework\FlagManager;

/**
 * Section-level dirty flags using Magento's flag table
 *
 * Each section (products, cms_pages, categories, metadata) has its own flag.
 * When an entity changes, only that section's flag is set dirty.
 * The cron job checks flags and only regenerates if at least one is dirty.
 *
 * Uses Magento's built-in FlagManager which stores data in the `flag` table.
 * No custom tables needed.
 */
class DirtyFlag
{
    private const FLAG_PREFIX = 'rkd_llmstxt_dirty_';

    public const SECTION_PRODUCTS = 'products';
    public const SECTION_CMS_PAGES = 'cms_pages';
    public const SECTION_CATEGORIES = 'categories';
    public const SECTION_METADATA = 'metadata';

    public function __construct(
        private readonly FlagManager $flagManager
    ) {
    }

    /**
     * Mark a section as dirty (needs regeneration)
     *
     * @param string $section One of the SECTION_* constants
     * @return void
     */
    public function markDirty(string $section): void
    {
        $this->flagManager->saveFlag(
            self::FLAG_PREFIX . $section,
            true
        );
    }

    /**
     * Check if a section is dirty
     *
     * @param string $section
     * @return bool
     */
    public function isDirty(string $section): bool
    {
        return (bool) $this->flagManager->getFlagData(self::FLAG_PREFIX . $section);
    }

    /**
     * Check if any section is dirty
     *
     * @return bool
     */
    public function isAnyDirty(): bool
    {
        return $this->isDirty(self::SECTION_PRODUCTS)
            || $this->isDirty(self::SECTION_CMS_PAGES)
            || $this->isDirty(self::SECTION_CATEGORIES)
            || $this->isDirty(self::SECTION_METADATA);
    }

    /**
     * Clear dirty flag for a section (after regeneration)
     *
     * @param string $section
     * @return void
     */
    public function clearDirty(string $section): void
    {
        $this->flagManager->saveFlag(
            self::FLAG_PREFIX . $section,
            false
        );
    }

    /**
     * Clear all dirty flags (after full regeneration)
     *
     * @return void
     */
    public function clearAll(): void
    {
        $this->clearDirty(self::SECTION_PRODUCTS);
        $this->clearDirty(self::SECTION_CMS_PAGES);
        $this->clearDirty(self::SECTION_CATEGORIES);
        $this->clearDirty(self::SECTION_METADATA);
    }
}
