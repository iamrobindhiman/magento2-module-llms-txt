<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Product;

use Magento\Framework\App\ResourceConnection;
use RKD\LlmsTxt\Model\EavEntityTypeResolver;

/**
 * Batch-loads variant and option data for product types
 *
 * Performance: All methods use single SQL queries with IN() clauses.
 * No N+1 — one query per product type per batch, regardless of batch size.
 *
 * Returns hash maps (product_id => data) for O(1) lookup per product.
 */
class VariantDataLoader
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EavEntityTypeResolver $entityTypeResolver
    ) {
    }

    /**
     * Load configurable product options (color, size, etc.)
     *
     * Returns: [product_id => ['Color' => ['Red', 'Blue'], 'Size' => ['S', 'M', 'L']]]
     *
     * Uses 1 query joining:
     *   catalog_product_super_attribute → eav_attribute → eav_attribute_option_value
     *   + catalog_product_super_link for child product filtering
     *
     * @param int[] $productIds Configurable product entity IDs
     * @param int $storeId
     * @return array<int, array<string, string[]>>
     */
    public function loadConfigurableOptions(array $productIds, int $storeId): array
    {
        if (empty($productIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                ['cpsa' => $this->resourceConnection->getTableName('catalog_product_super_attribute')],
                ['cpsa.product_id']
            )
            ->join(
                ['ea' => $this->resourceConnection->getTableName('eav_attribute')],
                'ea.attribute_id = cpsa.attribute_id',
                ['attribute_code' => 'ea.frontend_label']
            )
            ->join(
                ['cpsl' => $this->resourceConnection->getTableName('catalog_product_super_link')],
                'cpsl.parent_id = cpsa.product_id',
                []
            )
            ->join(
                ['cpei' => $this->resourceConnection->getTableName('catalog_product_entity_int')],
                'cpei.entity_id = cpsl.product_id AND cpei.attribute_id = cpsa.attribute_id',
                ['option_id' => 'cpei.value']
            )
            ->joinLeft(
                ['eaov' => $this->resourceConnection->getTableName('eav_attribute_option_value')],
                'eaov.option_id = cpei.value AND eaov.store_id = ' . (int) $storeId,
                []
            )
            ->joinLeft(
                ['eaov_default' => $this->resourceConnection->getTableName('eav_attribute_option_value')],
                'eaov_default.option_id = cpei.value AND eaov_default.store_id = 0',
                ['option_label' => $connection->getIfNullSql('eaov.value', 'eaov_default.value')]
            )
            ->where('cpsa.product_id IN (?)', $productIds)
            ->group(['cpsa.product_id', 'ea.frontend_label', 'cpei.value']);

        $rows = $connection->fetchAll($select);

        $result = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            $attributeLabel = (string) $row['attribute_code'];
            $optionLabel = (string) $row['option_label'];

            if ($attributeLabel === '' || $optionLabel === '') {
                continue;
            }

            if (!isset($result[$productId][$attributeLabel])) {
                $result[$productId][$attributeLabel] = [];
            }

            if (!in_array($optionLabel, $result[$productId][$attributeLabel], true)) {
                $result[$productId][$attributeLabel][] = $optionLabel;
            }
        }

        return $result;
    }

    /**
     * Load bundle product selections (included items)
     *
     * Returns: [product_id => ['Option Name' => ['Item 1', 'Item 2']]]
     *
     * @param int[] $productIds Bundle product entity IDs
     * @param int $storeId
     * @return array<int, array<string, string[]>>
     */
    public function loadBundleSelections(array $productIds, int $storeId): array
    {
        if (empty($productIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        $select = $connection->select()
            ->from(
                ['cpo' => $this->resourceConnection->getTableName('catalog_product_bundle_option')],
                ['parent_product_id' => 'cpo.parent_id']
            )
            ->joinLeft(
                ['cpov' => $this->resourceConnection->getTableName('catalog_product_bundle_option_value')],
                'cpov.option_id = cpo.option_id AND cpov.store_id = ' . (int) $storeId,
                []
            )
            ->joinLeft(
                ['cpov_default' => $this->resourceConnection->getTableName('catalog_product_bundle_option_value')],
                'cpov_default.option_id = cpo.option_id AND cpov_default.store_id = 0',
                ['option_title' => $connection->getIfNullSql('cpov.title', 'cpov_default.title')]
            )
            ->join(
                ['cpbs' => $this->resourceConnection->getTableName('catalog_product_bundle_selection')],
                'cpbs.option_id = cpo.option_id',
                []
            )
            ->join(
                ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'cpe.entity_id = cpbs.product_id',
                []
            )
            ->join(
                ['cpev' => $this->resourceConnection->getTableName('catalog_product_entity_varchar')],
                'cpev.entity_id = cpe.entity_id AND cpev.attribute_id = '
                . '(SELECT attribute_id FROM '
                . $this->resourceConnection->getTableName('eav_attribute')
                . ' WHERE attribute_code = \'name\' AND entity_type_id = ' . $this->entityTypeResolver->getProductEntityTypeId() . ')'
                . ' AND cpev.store_id = 0',
                ['child_name' => 'cpev.value']
            )
            ->where('cpo.parent_id IN (?)', $productIds);

        $rows = $connection->fetchAll($select);

        $result = [];
        foreach ($rows as $row) {
            $productId = (int) $row['parent_product_id'];
            $optionTitle = (string) $row['option_title'];
            $childName = (string) $row['child_name'];

            if ($optionTitle === '' || $childName === '') {
                continue;
            }

            if (!isset($result[$productId][$optionTitle])) {
                $result[$productId][$optionTitle] = [];
            }
            $result[$productId][$optionTitle][] = $childName;
        }

        return $result;
    }

    /**
     * Load grouped product associated items
     *
     * Returns: [product_id => [['name' => 'Item', 'price' => 14.00], ...]]
     *
     * @param int[] $productIds Grouped product entity IDs
     * @return array<int, array<int, array{name: string, price: float}>>
     */
    public function loadGroupedAssociations(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        // Link type 3 = associated (grouped)
        $select = $connection->select()
            ->from(
                ['cpl' => $this->resourceConnection->getTableName('catalog_product_link')],
                ['parent_id' => 'cpl.product_id']
            )
            ->join(
                ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                'cpe.entity_id = cpl.linked_product_id',
                []
            )
            ->join(
                ['cpev' => $this->resourceConnection->getTableName('catalog_product_entity_varchar')],
                'cpev.entity_id = cpe.entity_id AND cpev.attribute_id = '
                . '(SELECT attribute_id FROM '
                . $this->resourceConnection->getTableName('eav_attribute')
                . ' WHERE attribute_code = \'name\' AND entity_type_id = ' . $this->entityTypeResolver->getProductEntityTypeId() . ')'
                . ' AND cpev.store_id = 0',
                ['child_name' => 'cpev.value']
            )
            ->joinLeft(
                ['cped' => $this->resourceConnection->getTableName('catalog_product_entity_decimal')],
                'cped.entity_id = cpe.entity_id AND cped.attribute_id = '
                . '(SELECT attribute_id FROM '
                . $this->resourceConnection->getTableName('eav_attribute')
                . ' WHERE attribute_code = \'price\' AND entity_type_id = ' . $this->entityTypeResolver->getProductEntityTypeId() . ')'
                . ' AND cped.store_id = 0',
                ['child_price' => 'cped.value']
            )
            ->where('cpl.product_id IN (?)', $productIds)
            ->where('cpl.link_type_id = ?', 3);

        $rows = $connection->fetchAll($select);

        $result = [];
        foreach ($rows as $row) {
            $productId = (int) $row['parent_id'];
            $result[$productId][] = [
                'name' => (string) $row['child_name'],
                'price' => (float) ($row['child_price'] ?? 0),
            ];
        }

        return $result;
    }

    /**
     * Load customizable options (add-on services, text fields, etc.)
     *
     * Returns: [product_id => [['title' => 'Installation', 'type' => 'drop_down', 'values' => [...]]]]
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array>
     */
    public function loadCustomOptions(array $productIds, int $storeId): array
    {
        if (empty($productIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        // Load option titles
        $selectOptions = $connection->select()
            ->from(
                ['cpo' => $this->resourceConnection->getTableName('catalog_product_option')],
                ['option_id', 'product_id', 'type']
            )
            ->joinLeft(
                ['cpot' => $this->resourceConnection->getTableName('catalog_product_option_title')],
                'cpot.option_id = cpo.option_id AND cpot.store_id = ' . (int) $storeId,
                []
            )
            ->joinLeft(
                ['cpot_default' => $this->resourceConnection->getTableName('catalog_product_option_title')],
                'cpot_default.option_id = cpo.option_id AND cpot_default.store_id = 0',
                ['title' => $connection->getIfNullSql('cpot.title', 'cpot_default.title')]
            )
            ->where('cpo.product_id IN (?)', $productIds);

        $options = $connection->fetchAll($selectOptions);

        if (empty($options)) {
            return [];
        }

        // Collect option IDs that have selectable values (drop_down, radio, checkbox, multi)
        $selectableOptionIds = [];
        $optionMap = [];
        foreach ($options as $option) {
            $optionId = (int) $option['option_id'];
            $productId = (int) $option['product_id'];
            $optionMap[$optionId] = [
                'product_id' => $productId,
                'title' => (string) $option['title'],
                'type' => (string) $option['type'],
                'values' => [],
            ];
            if (in_array($option['type'], ['drop_down', 'radio', 'checkbox', 'multiple'], true)) {
                $selectableOptionIds[] = $optionId;
            }
        }

        // Load option values for selectable types
        if (!empty($selectableOptionIds)) {
            $selectValues = $connection->select()
                ->from(
                    ['cpotv' => $this->resourceConnection->getTableName('catalog_product_option_type_value')],
                    ['option_id', 'option_type_id']
                )
                ->joinLeft(
                    ['cpotp' => $this->resourceConnection->getTableName('catalog_product_option_type_price')],
                    'cpotp.option_type_id = cpotv.option_type_id AND cpotp.store_id = 0',
                    ['price' => 'cpotp.price']
                )
                ->joinLeft(
                    ['cpott' => $this->resourceConnection->getTableName('catalog_product_option_type_title')],
                    'cpott.option_type_id = cpotv.option_type_id AND cpott.store_id = '
                    . (int) $storeId,
                    []
                )
                ->joinLeft(
                    ['cpott_default' => $this->resourceConnection->getTableName('catalog_product_option_type_title')],
                    'cpott_default.option_type_id = cpotv.option_type_id AND cpott_default.store_id = 0',
                    ['value_title' => $connection->getIfNullSql('cpott.title', 'cpott_default.title')]
                )
                ->where('cpotv.option_id IN (?)', $selectableOptionIds);

            $values = $connection->fetchAll($selectValues);

            foreach ($values as $value) {
                $optionId = (int) $value['option_id'];
                if (isset($optionMap[$optionId])) {
                    $valueTitle = (string) $value['value_title'];
                    $price = (float) ($value['price'] ?? 0);
                    if ($valueTitle !== '') {
                        $optionMap[$optionId]['values'][] = [
                            'title' => $valueTitle,
                            'price' => $price,
                        ];
                    }
                }
            }
        }

        // Group by product_id
        $result = [];
        foreach ($optionMap as $option) {
            $productId = $option['product_id'];
            $result[$productId][] = $option;
        }

        return $result;
    }
}
