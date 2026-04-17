<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model;

use Magento\Eav\Model\Config as EavConfig;

/**
 * Resolves EAV entity type IDs dynamically instead of hardcoding magic numbers
 *
 * Caches the resolved IDs for the lifetime of the request.
 * Replaces all hardcoded `entity_type_id = 4` (product) and `entity_type_id = 3` (category).
 */
class EavEntityTypeResolver
{
    /**
     * @var array<string, int>
     */
    private array $cache = [];

    public function __construct(
        private readonly EavConfig $eavConfig
    ) {
    }

    /**
     * Get the product entity type ID
     *
     * @return int
     */
    public function getProductEntityTypeId(): int
    {
        return $this->resolve(\Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE);
    }

    /**
     * Get the category entity type ID
     *
     * @return int
     */
    public function getCategoryEntityTypeId(): int
    {
        return $this->resolve(\Magento\Catalog\Api\Data\CategoryAttributeInterface::ENTITY_TYPE_CODE);
    }

    /**
     * Resolve entity type code to ID with caching
     *
     * @param string $entityTypeCode
     * @return int
     */
    private function resolve(string $entityTypeCode): int
    {
        if (!isset($this->cache[$entityTypeCode])) {
            $this->cache[$entityTypeCode] = (int) $this->eavConfig
                ->getEntityType($entityTypeCode)
                ->getEntityTypeId();
        }

        return $this->cache[$entityTypeCode];
    }
}
