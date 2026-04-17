<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Api;

use RKD\LlmsTxt\Api\Data\GenerationResultInterface;

/**
 * LLMs.txt Generator Service Contract
 *
 * @api
 */
interface LlmsTxtGeneratorInterface
{
    /**
     * Generate llms.txt and/or llms-full.txt for a store
     *
     * @param int|null $storeId
     * @param string $trigger manual|cron|api
     * @return \RKD\LlmsTxt\Api\Data\GenerationResultInterface
     */
    public function generate(?int $storeId = null, string $trigger = 'manual'): GenerationResultInterface;

    /**
     * Generate preview without writing files
     *
     * @param int|null $storeId
     * @return string
     */
    public function preview(?int $storeId = null): string;

    /**
     * Validate existing llms.txt against the specification
     *
     * @param int|null $storeId
     * @return string[] Array of validation errors (empty = valid)
     */
    public function validate(?int $storeId = null): array;
}
