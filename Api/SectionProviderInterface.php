<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Api;

use RKD\LlmsTxt\Api\Data\SectionInterface;

/**
 * Section Provider Contract
 *
 * Each content type (CMS pages, categories, products, metadata)
 * implements this interface to contribute sections to the llms.txt output.
 *
 * @api
 */
interface SectionProviderInterface
{
    /**
     * Get the section name for llms.txt output
     *
     * @return string
     */
    public function getSectionName(): string;

    /**
     * Get section priority (lower number = higher in file)
     *
     * @return int
     */
    public function getPriority(): int;

    /**
     * Generate section content for a given store
     *
     * @param int $storeId
     * @return \RKD\LlmsTxt\Api\Data\SectionInterface
     */
    public function getSection(int $storeId): SectionInterface;

    /**
     * Check if this section is enabled in admin config
     *
     * @param int $storeId
     * @return bool
     */
    public function isEnabled(int $storeId): bool;
}
