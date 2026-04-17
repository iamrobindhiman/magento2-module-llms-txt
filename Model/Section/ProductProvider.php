<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Section;

use Generator;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogInventory\Model\Stock;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Store\Model\StoreManagerInterface;
use RKD\LlmsTxt\Api\Data\SectionInterface;
use RKD\LlmsTxt\Api\Data\SectionInterfaceFactory;
use RKD\LlmsTxt\Api\SectionProviderInterface;
use RKD\LlmsTxt\Model\Config;
use RKD\LlmsTxt\Model\EavEntityTypeResolver;
use RKD\LlmsTxt\Model\Product\CategoryMapper;
use RKD\LlmsTxt\Model\Product\ExtraAttributeLoader;
use RKD\LlmsTxt\Model\Product\VariantDataLoader;
use RKD\LlmsTxt\Model\TextSanitizer;

/**
 * Provides product catalog section for llms.txt
 *
 * Performance Architecture:
 * - Cursor-based pagination: WHERE entity_id > :lastId — avoids OFFSET scan degradation on large catalogs
 * - PHP Generator (yield): per-batch memory bounded; each batch released before the next is loaded
 * - Batch variant loading: 1 query per product type per batch (not per product)
 * - Default safety cap: 10,000 products (configurable); raise with matching PHP memory_limit increase
 *
 * Supports: Simple, Configurable, Bundle, Grouped, Virtual, Downloadable + Customizable Options
 */
class ProductProvider implements SectionProviderInterface
{
    /**
     * Number of products to load per cursor batch
     */
    private const BATCH_SIZE = 1000;

    public function __construct(
        private readonly SectionInterfaceFactory $sectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly VariantDataLoader $variantDataLoader,
        private readonly ExtraAttributeLoader $extraAttributeLoader,
        private readonly CategoryMapper $categoryMapper,
        private readonly Config $config,
        private readonly TextSanitizer $sanitizer,
        private readonly EavEntityTypeResolver $entityTypeResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getSectionName(): string
    {
        return 'Products';
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return 40;
    }

    /**
     * @inheritdoc
     */
    public function getSection(int $storeId): SectionInterface
    {
        $store = $this->storeManager->getStore($storeId);
        $baseUrl = rtrim($store->getBaseUrl(), '/');
        $rootCategoryId = (int) $store->getRootCategoryId();
        $excludeOutOfStock = $this->config->isExcludeOutOfStock($storeId);
        // product_limit: 0 = unlimited (include full catalog); >0 = cap at that count
        $productLimit = $this->config->getProductLimit($storeId);
        $hasProductCap = $productLimit > 0;
        $includePrice = $this->config->isIncludePrice($storeId);
        $includeSku = $this->config->isIncludeSku($storeId);
        $includeShortDesc = $this->config->isIncludeShortDescription($storeId);
        $contentDepth = $this->config->getContentDepth($storeId);
        $extraAttributeCodes = $this->config->getExtraAttributes($storeId);
        $currency = $this->config->getStoreCurrency($storeId);

        // FIX-07: Group products by category
        // Key = category breadcrumb, Value = array of link entries
        $groupedLinks = [];
        $fullContentParts = [];
        $productCount = 0;

        foreach ($this->getProductGenerator($storeId, $excludeOutOfStock) as $batch) {
            $configurableIds = [];
            $bundleIds = [];
            $groupedIds = [];
            $allIds = [];

            foreach ($batch as $row) {
                $entityId = (int) $row['entity_id'];
                $typeId = (string) $row['type_id'];
                $allIds[] = $entityId;

                if ($typeId === Type::TYPE_SIMPLE && isset($row['parent_type'])
                    && $row['parent_type'] === 'configurable') {
                    continue;
                }

                switch ($typeId) {
                    case 'configurable':
                        $configurableIds[] = $entityId;
                        break;
                    case 'bundle':
                        $bundleIds[] = $entityId;
                        break;
                    case 'grouped':
                        $groupedIds[] = $entityId;
                        break;
                }
            }

            // Batch load all variant/extra data
            $configurableOptions = $this->variantDataLoader->loadConfigurableOptions($configurableIds, $storeId);
            $bundleSelections = $this->variantDataLoader->loadBundleSelections($bundleIds, $storeId);
            $groupedAssociations = $this->variantDataLoader->loadGroupedAssociations($groupedIds);
            $customOptions = $this->variantDataLoader->loadCustomOptions($allIds, $storeId);
            $extraAttributes = $this->extraAttributeLoader->loadAttributes($allIds, $extraAttributeCodes, $storeId);

            // FIX-07: Batch load category mapping for this batch
            $categoryMap = $this->categoryMapper->getProductCategoryMap($allIds, $storeId, $rootCategoryId);

            foreach ($batch as $row) {
                if ($hasProductCap && $productCount >= $productLimit) {
                    break 2;
                }

                $entityId = (int) $row['entity_id'];
                $name = $this->sanitizer->sanitize((string) ($row['name'] ?? ''));
                $urlKey = (string) ($row['url_key'] ?? '');
                $typeId = (string) $row['type_id'];

                if ($name === '' || $urlKey === '') {
                    continue;
                }

                $url = $baseUrl . '/' . $urlKey . $this->config->getProductUrlSuffix($storeId);
                $productCount++;

                $productExtras = $extraAttributes[$entityId] ?? [];
                $categoryBreadcrumb = $categoryMap[$entityId] ?? 'Other';

                $linkDesc = $this->buildLinkDescription(
                    $row,
                    $includeSku,
                    $includePrice,
                    $currency,
                    $typeId,
                    $entityId,
                    $configurableOptions,
                    $bundleSelections,
                    $groupedAssociations,
                    $productExtras
                );

                $linkText = $name . ($linkDesc !== '' ? ' — ' . $linkDesc : '');

                // Group by category
                if (!isset($groupedLinks[$categoryBreadcrumb])) {
                    $groupedLinks[$categoryBreadcrumb] = [];
                }
                $groupedLinks[$categoryBreadcrumb][$linkText] = $url;

                $fullContentParts[] = $this->buildFullContent(
                    $row,
                    $url,
                    $includeSku,
                    $includePrice,
                    $includeShortDesc,
                    $contentDepth,
                    $currency,
                    $typeId,
                    $entityId,
                    $configurableOptions,
                    $bundleSelections,
                    $groupedAssociations,
                    $customOptions,
                    $productExtras
                );
            }

            unset(
                $batch,
                $configurableOptions,
                $bundleSelections,
                $groupedAssociations,
                $customOptions,
                $extraAttributes,
                $categoryMap
            );
        }

        // Build grouped links for llms.txt
        $flatLinks = [];
        $linksSummaryParts = [];
        foreach ($groupedLinks as $category => $categoryLinks) {
            $count = count($categoryLinks);
            $linksSummaryParts[] = sprintf('%s (%d)', $category, $count);
            // Add a category sub-heading marker (### prefix recognized by Generator)
            $flatLinks['### ' . $category . ' (' . $count . ' products)'] = '';
            foreach ($categoryLinks as $title => $url) {
                $flatLinks[$title] = $url;
            }
        }

        $summary = $excludeOutOfStock
            ? sprintf('Product catalog (%d in-stock products)', $productCount)
            : sprintf('Product catalog (%d products)', $productCount);

        $warnings = [];
        if ($hasProductCap && $productCount >= $productLimit) {
            $totalAvailable = $this->countAvailableProducts($storeId, $excludeOutOfStock);
            if ($totalAvailable > $productCount) {
                $warnings[] = sprintf(
                    'Product Limit reached: included %d of %d visible products. '
                    . 'Set Stores > Configuration > RKD > LLMs.txt > Product Limit to 0 (unlimited) '
                    . 'or raise the cap to include more. For very large catalogs, ensure PHP memory_limit is adequate.',
                    $productCount,
                    $totalAvailable
                );
            }
        }

        $section = $this->sectionFactory->create();
        $section->setName($this->getSectionName())
            ->setPriority($this->getPriority())
            ->setSummary($summary)
            ->setLinks($flatLinks)
            ->setFullContent(implode("\n\n---\n\n", $fullContentParts))
            ->setItemCount($productCount)
            ->setWarnings($warnings);

        return $section;
    }

    /**
     * Count total visible+enabled products matching the same filters the cursor loop uses
     */
    private function countAvailableProducts(int $storeId, bool $excludeOutOfStock): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $this->buildProductSelect($connection, $storeId, $excludeOutOfStock, 0);
        $countSelect = $connection->select()
            ->from(['product_subquery' => $select], ['total' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')]);
        return (int) $connection->fetchOne($countSelect);
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(int $storeId): bool
    {
        return $this->config->isEnabled($storeId) && $this->config->isIncludeProducts($storeId);
    }

    /**
     * Generator: yields batches of product rows using cursor-based pagination
     *
     * Uses WHERE entity_id > :lastId instead of OFFSET.
     * Each yield returns an array of up to BATCH_SIZE raw rows.
     * Per-batch memory is bounded; the caller is responsible for releasing batch data.
     *
     * Note: Products are ordered by entity_id for cursor stability.
     * Sort order configuration applies post-collection in future versions.
     *
     * @param int $storeId
     * @param bool $excludeOutOfStock
     * @return Generator<int, array>
     */
    private function getProductGenerator(int $storeId, bool $excludeOutOfStock): Generator
    {
        $connection = $this->resourceConnection->getConnection();
        $lastId = 0;

        while (true) {
            $select = $this->buildProductSelect($connection, $storeId, $excludeOutOfStock, $lastId);
            $select->limit(self::BATCH_SIZE);

            $rows = $connection->fetchAll($select);

            if (empty($rows)) {
                break;
            }

            // Update cursor to last entity_id in this batch
            $lastRow = end($rows);
            $lastId = (int) $lastRow['entity_id'];

            yield $rows;

            // If we got fewer rows than batch size, we've reached the end
            if (count($rows) < self::BATCH_SIZE) {
                break;
            }
        }
    }

    /**
     * Build the product SELECT query with cursor position
     *
     * Joins: catalog_product_entity (flat) + EAV attributes + optional stock status
     * All filters applied at SQL level for maximum performance.
     */
    private function buildProductSelect(
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        int $storeId,
        bool $excludeOutOfStock,
        int $lastId
    ): Select {
        $select = $connection->select()
            ->from(
                ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                ['entity_id', 'sku', 'type_id']
            );

        // Join name attribute (EAV varchar, store-scoped with default fallback)
        $this->joinEavAttribute($select, $connection, 'name', 'varchar', $storeId);

        // Join url_key attribute
        $this->joinEavAttribute($select, $connection, 'url_key', 'varchar', $storeId);

        // Join status attribute (must be enabled)
        $this->joinEavAttribute($select, $connection, 'status', 'int', $storeId, 'status_val');
        $select->where(
            $connection->getIfNullSql('status_val_store.value', 'status_val_default.value') . ' = ?',
            Status::STATUS_ENABLED
        );

        // Join visibility attribute (must be visible in catalog or both)
        $this->joinEavAttribute($select, $connection, 'visibility', 'int', $storeId, 'vis_val');
        $select->where(
            $connection->getIfNullSql('vis_val_store.value', 'vis_val_default.value') . ' IN (?)',
            [Visibility::VISIBILITY_BOTH, Visibility::VISIBILITY_IN_CATALOG]
        );

        // Join price attribute
        $this->joinEavAttribute($select, $connection, 'price', 'decimal', $storeId, 'price_val');

        // Join short_description attribute
        $this->joinEavAttribute($select, $connection, 'short_description', 'text', $storeId, 'short_desc');

        // Join full description attribute (FIX-08: product descriptions for llms-full.txt)
        $this->joinEavAttribute($select, $connection, 'description', 'text', $storeId, 'full_desc');

        // Inventory filter: JOIN stock status table (single JOIN, not subquery)
        if ($excludeOutOfStock) {
            $select->join(
                ['ciss' => $this->resourceConnection->getTableName('cataloginventory_stock_status')],
                'ciss.product_id = cpe.entity_id AND ciss.stock_status = ' . Stock::STOCK_IN_STOCK,
                []
            );
        }

        // Cursor-based pagination: jump directly to the right position via PK index
        $select->where('cpe.entity_id > ?', $lastId);
        $select->order('cpe.entity_id ASC');

        return $select;
    }

    /**
     * Join an EAV attribute with store-scoped value + default fallback
     *
     * Pattern: LEFT JOIN store-scoped value, LEFT JOIN default value, use IFNULL
     */
    private function joinEavAttribute(
        Select $select,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        string $attributeCode,
        string $backendType,
        int $storeId,
        ?string $alias = null
    ): void {
        $alias = $alias ?? $attributeCode;
        $tableSuffix = $backendType === 'text' ? 'text' : $backendType;
        $table = $this->resourceConnection->getTableName('catalog_product_entity_' . $tableSuffix);
        $eavTable = $this->resourceConnection->getTableName('eav_attribute');

        // Store-scoped value
        $select->joinLeft(
            [$alias . '_store' => $table],
            $alias . '_store.entity_id = cpe.entity_id'
            . ' AND ' . $alias . '_store.store_id = ' . (int) $storeId
            . ' AND ' . $alias . '_store.attribute_id = '
            . '(SELECT attribute_id FROM ' . $eavTable
            . ' WHERE attribute_code = ' . $connection->quote($attributeCode)
            . ' AND entity_type_id = ' . $this->entityTypeResolver->getProductEntityTypeId() . ')',
            []
        );

        // Default value (store_id = 0)
        $select->joinLeft(
            [$alias . '_default' => $table],
            $alias . '_default.entity_id = cpe.entity_id'
            . ' AND ' . $alias . '_default.store_id = 0'
            . ' AND ' . $alias . '_default.attribute_id = '
            . '(SELECT attribute_id FROM ' . $eavTable
            . ' WHERE attribute_code = ' . $connection->quote($attributeCode)
            . ' AND entity_type_id = ' . $this->entityTypeResolver->getProductEntityTypeId() . ')',
            []
        );

        // Resolved column: store value with default fallback
        $resolvedExpr = $connection->getIfNullSql(
            $alias . '_store.value',
            $alias . '_default.value'
        );
        $select->columns([$attributeCode => new \Magento\Framework\DB\Sql\Expression($resolvedExpr)]);
    }

    /**
     * Build concise description for llms.txt link line
     *
     * BUG-01/02: All text sanitized via TextSanitizer.
     */
    private function buildLinkDescription(
        array $row,
        bool $includeSku,
        bool $includePrice,
        string $currency,
        string $typeId,
        int $entityId,
        array $configurableOptions,
        array $bundleSelections,
        array $groupedAssociations,
        array $productExtras
    ): string {
        $parts = [];

        if ($includeSku) {
            $sku = $this->sanitizer->sanitize((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $parts[] = 'SKU: ' . $sku;
            }
        }

        if ($includePrice) {
            $price = (float) ($row['price'] ?? 0);
            if ($price > 0) {
                $parts[] = $this->formatPrice($price, $currency);
            }
        }

        // Admin-selected extra attributes (niche-adaptive)
        foreach ($productExtras as $label => $value) {
            $parts[] = $label . ': ' . $this->sanitizer->sanitize($value);
        }

        // Add variant summary for configurable products
        if ($typeId === 'configurable' && isset($configurableOptions[$entityId])) {
            $optionSummaries = [];
            foreach ($configurableOptions[$entityId] as $attrLabel => $values) {
                $optionSummaries[] = $attrLabel . ': ' . implode(', ', $values);
            }
            if (!empty($optionSummaries)) {
                $parts[] = implode(' | ', $optionSummaries);
            }
        }

        // Add bundle summary
        if ($typeId === 'bundle' && isset($bundleSelections[$entityId])) {
            $items = [];
            foreach ($bundleSelections[$entityId] as $optionItems) {
                foreach ($optionItems as $item) {
                    $items[] = $this->sanitizer->sanitize($item);
                }
            }
            if (!empty($items)) {
                $parts[] = 'Includes: ' . implode(', ', array_slice($items, 0, 5));
                if (count($items) > 5) {
                    $parts[array_key_last($parts)] .= ' + ' . (count($items) - 5) . ' more';
                }
            }
        }

        // Add grouped summary
        if ($typeId === 'grouped' && isset($groupedAssociations[$entityId])) {
            $items = [];
            foreach ($groupedAssociations[$entityId] as $assoc) {
                $itemStr = $this->sanitizer->sanitize($assoc['name']);
                if ($assoc['price'] > 0) {
                    $itemStr .= ' (' . $this->formatPrice($assoc['price'], $currency) . ')';
                }
                $items[] = $itemStr;
            }
            if (!empty($items)) {
                $parts[] = 'Includes: ' . implode(', ', $items);
            }
        }

        // Mark virtual/downloadable
        if ($typeId === 'virtual') {
            $parts[] = 'Digital';
        }
        if ($typeId === 'downloadable') {
            $parts[] = 'Download';
        }

        return implode(' | ', $parts);
    }

    /**
     * Build detailed product content for llms-full.txt
     *
     * FIX-08: Includes short_description and full description.
     * BUG-01/02: All text sanitized via TextSanitizer.
     */
    private function buildFullContent(
        array $row,
        string $url,
        bool $includeSku,
        bool $includePrice,
        bool $includeShortDesc,
        string $contentDepth,
        string $currency,
        string $typeId,
        int $entityId,
        array $configurableOptions,
        array $bundleSelections,
        array $groupedAssociations,
        array $customOptions,
        array $productExtras
    ): string {
        $name = $this->sanitizer->sanitize((string) ($row['name'] ?? ''));
        $lines = ['### ' . $name, '', 'URL: ' . $url];

        if ($includeSku) {
            $sku = $this->sanitizer->sanitize((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $lines[] = 'SKU: ' . $sku;
            }
        }

        if ($includePrice) {
            $price = (float) ($row['price'] ?? 0);
            if ($price > 0) {
                $lines[] = 'Price: ' . $this->formatPrice($price, $currency);
            }
        }

        // Admin-selected extra attributes (niche-adaptive)
        foreach ($productExtras as $label => $value) {
            $lines[] = $label . ': ' . $this->sanitizer->sanitize($value);
        }

        // Product type label
        if ($typeId !== Type::TYPE_SIMPLE) {
            $typeLabels = [
                'configurable' => 'Configurable Product',
                'bundle' => 'Bundle Product',
                'grouped' => 'Grouped Product',
                'virtual' => 'Virtual Product (Digital)',
                'downloadable' => 'Downloadable Product',
            ];
            $lines[] = 'Type: ' . ($typeLabels[$typeId] ?? ucfirst($typeId));
        }

        // Configurable options
        if ($typeId === 'configurable' && isset($configurableOptions[$entityId])) {
            foreach ($configurableOptions[$entityId] as $attrLabel => $values) {
                $lines[] = 'Available ' . $attrLabel . ': ' . implode(', ', $values);
            }
        }

        // Bundle selections
        if ($typeId === 'bundle' && isset($bundleSelections[$entityId])) {
            $lines[] = '';
            $lines[] = '**Bundle includes:**';
            foreach ($bundleSelections[$entityId] as $optionTitle => $items) {
                $sanitizedItems = array_map([$this->sanitizer, 'sanitize'], $items);
                $lines[] = '- ' . $this->sanitizer->sanitize($optionTitle) . ': '
                    . implode(', ', $sanitizedItems);
            }
        }

        // Grouped associations
        if ($typeId === 'grouped' && isset($groupedAssociations[$entityId])) {
            $lines[] = '';
            $lines[] = '**Set includes:**';
            foreach ($groupedAssociations[$entityId] as $assoc) {
                $assocName = $this->sanitizer->sanitize($assoc['name']);
                $priceStr = $assoc['price'] > 0 ? ' — ' . $this->formatPrice($assoc['price'], $currency) : '';
                $lines[] = '- ' . $assocName . $priceStr;
            }
        }

        // Customizable options (add-on services, etc.)
        if (isset($customOptions[$entityId]) && !empty($customOptions[$entityId])) {
            $lines[] = '';
            $lines[] = '**Customization options:**';
            foreach ($customOptions[$entityId] as $option) {
                $optionStr = '- ' . $this->sanitizer->sanitize($option['title']);
                if (!empty($option['values'])) {
                    $valueStrs = [];
                    foreach ($option['values'] as $value) {
                        $vStr = $this->sanitizer->sanitize($value['title']);
                        if ($value['price'] > 0) {
                            $vStr .= ' (+' . $this->formatPrice($value['price'], $currency) . ')';
                        }
                        $valueStrs[] = $vStr;
                    }
                    $optionStr .= ': ' . implode(', ', $valueStrs);
                }
                $lines[] = $optionStr;
            }
        }

        // Product descriptions — llms-full.txt is the "full" file, so we never truncate.
        // 'summary' mode: descriptions omitted (attributes only)
        // 'detailed' mode: short_description if present, else full description (complete, untruncated)
        // 'complete' mode: both short_description AND full description when they differ
        if ($contentDepth === 'detailed' || $contentDepth === 'complete') {
            $shortDesc = $this->sanitizer->stripHtml((string) ($row['short_description'] ?? ''));
            $fullDesc = $this->sanitizer->stripHtml((string) ($row['description'] ?? ''));

            if ($shortDesc !== '') {
                $lines[] = '';
                $lines[] = $shortDesc;
            }

            if ($contentDepth === 'complete' && $fullDesc !== '' && $fullDesc !== $shortDesc) {
                $lines[] = '';
                $lines[] = $fullDesc;
            } elseif ($contentDepth === 'detailed' && $shortDesc === '' && $fullDesc !== '') {
                $lines[] = '';
                $lines[] = $fullDesc;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Format price with currency code
     */
    private function formatPrice(float $price, string $currency): string
    {
        $symbol = $currency === 'USD' ? '$' : $currency . ' ';

        return $symbol . number_format($price, 2);
    }

}
