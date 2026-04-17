<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Product;

use Magento\Framework\App\ResourceConnection;
use RKD\LlmsTxt\Model\EavEntityTypeResolver;

/**
 * Batch-loads admin-selected extra product attributes
 *
 * Performance: Single query per backend type per batch.
 * Handles varchar, text, int (with option label lookup), decimal attributes.
 *
 * Returns: [product_id => ['Gender' => 'Men', 'Material' => 'Cotton', ...]]
 */
class ExtraAttributeLoader
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EavEntityTypeResolver $entityTypeResolver
    ) {
    }

    /**
     * Load extra attribute values for a batch of product IDs
     *
     * @param int[] $productIds
     * @param string[] $attributeCodes
     * @param int $storeId
     * @return array<int, array<string, string>> [product_id => [label => value]]
     */
    public function loadAttributes(array $productIds, array $attributeCodes, int $storeId): array
    {
        if (empty($productIds) || empty($attributeCodes)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $eavTable = $this->resourceConnection->getTableName('eav_attribute');

        // Get attribute metadata (id, code, label, backend_type, frontend_input)
        $select = $connection->select()
            ->from($eavTable, ['attribute_id', 'attribute_code', 'frontend_label', 'backend_type', 'frontend_input'])
            ->where('attribute_code IN (?)', $attributeCodes)
            ->where('entity_type_id = ?', $this->entityTypeResolver->getProductEntityTypeId());

        $attributes = $connection->fetchAll($select);

        if (empty($attributes)) {
            return [];
        }

        $result = [];

        // Group attributes by backend type for batched queries
        $byType = [];
        $attrMeta = [];
        foreach ($attributes as $attr) {
            $type = (string) $attr['backend_type'];
            $attrId = (int) $attr['attribute_id'];
            $byType[$type][] = $attrId;
            $attrMeta[$attrId] = $attr;
        }

        // Query each backend type table once
        foreach ($byType as $backendType => $attrIds) {
            if ($backendType === 'static') {
                continue; // Static attributes are on the main entity table
            }

            $tableName = $this->resourceConnection->getTableName('catalog_product_entity_' . $backendType);

            // Store-scoped values with default fallback
            $valueSelect = $connection->select()
                ->from(
                    ['v_default' => $tableName],
                    ['entity_id', 'attribute_id']
                )
                ->joinLeft(
                    ['v_store' => $tableName],
                    'v_store.entity_id = v_default.entity_id'
                    . ' AND v_store.attribute_id = v_default.attribute_id'
                    . ' AND v_store.store_id = ' . (int) $storeId,
                    []
                )
                ->columns([
                    'resolved_value' => $connection->getIfNullSql('v_store.value', 'v_default.value'),
                ])
                ->where('v_default.entity_id IN (?)', $productIds)
                ->where('v_default.attribute_id IN (?)', $attrIds)
                ->where('v_default.store_id = ?', 0);

            $rows = $connection->fetchAll($valueSelect);

            // Pre-collect ALL option IDs from select/multiselect attributes (M1 fix: avoid N+1)
            $allOptionIds = [];
            $rowsNeedingResolution = [];

            foreach ($rows as $index => $row) {
                $attrId = (int) $row['attribute_id'];
                $rawValue = (string) ($row['resolved_value'] ?? '');
                $meta = $attrMeta[$attrId] ?? null;

                if ($meta === null || $rawValue === '') {
                    continue;
                }

                $input = (string) $meta['frontend_input'];
                if (in_array($input, ['select', 'multiselect'], true)) {
                    $optIds = array_filter(array_map('intval', explode(',', $rawValue)));
                    foreach ($optIds as $optId) {
                        $allOptionIds[$optId] = true;
                    }
                    $rowsNeedingResolution[$index] = $optIds;
                }
            }

            // Batch resolve all option labels in ONE query
            $optionLabelMap = [];
            if (!empty($allOptionIds)) {
                $optionLabelMap = $this->batchResolveOptionLabels(
                    array_keys($allOptionIds),
                    $storeId
                );
            }

            // Map resolved values back to products
            foreach ($rows as $index => $row) {
                $productId = (int) $row['entity_id'];
                $attrId = (int) $row['attribute_id'];
                $rawValue = (string) ($row['resolved_value'] ?? '');
                $meta = $attrMeta[$attrId] ?? null;

                if ($meta === null || $rawValue === '') {
                    continue;
                }

                $label = (string) $meta['frontend_label'];

                // Apply label resolution for select/multiselect
                if (isset($rowsNeedingResolution[$index])) {
                    $labels = [];
                    foreach ($rowsNeedingResolution[$index] as $optId) {
                        if (isset($optionLabelMap[$optId]) && $optionLabelMap[$optId] !== '') {
                            $labels[] = $optionLabelMap[$optId];
                        }
                    }
                    $rawValue = implode(', ', $labels);
                }

                if ($rawValue !== '' && $label !== '') {
                    $result[$productId][$label] = $rawValue;
                }
            }
        }

        return $result;
    }

    /**
     * Batch resolve option IDs to label text in a single query
     *
     * Replaces the per-row resolveOptionLabels() that caused N+1.
     *
     * @param int[] $optionIds All option IDs to resolve
     * @param int $storeId
     * @return array<int, string> [option_id => label]
     */
    private function batchResolveOptionLabels(array $optionIds, int $storeId): array
    {
        if (empty($optionIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('eav_attribute_option_value');

        $select = $connection->select()
            ->from(
                ['v_default' => $table],
                ['option_id']
            )
            ->joinLeft(
                ['v_store' => $table],
                'v_store.option_id = v_default.option_id AND v_store.store_id = ' . (int) $storeId,
                []
            )
            ->columns([
                'label' => $connection->getIfNullSql('v_store.value', 'v_default.value'),
            ])
            ->where('v_default.option_id IN (?)', $optionIds)
            ->where('v_default.store_id = ?', 0);

        $rows = $connection->fetchAll($select);

        $labelMap = [];
        foreach ($rows as $row) {
            $optId = (int) $row['option_id'];
            $label = (string) ($row['label'] ?? '');
            if ($label !== '') {
                $labelMap[$optId] = $label;
            }
        }

        return $labelMap;
    }
}
