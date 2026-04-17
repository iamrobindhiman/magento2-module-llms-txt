<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Product;

use Magento\Framework\App\ResourceConnection;
use RKD\LlmsTxt\Model\EavEntityTypeResolver;

/**
 * Maps products to their primary (deepest) category for grouping in llms.txt
 *
 * Performance: Single query per batch using catalog_category_product table.
 * Returns the deepest assigned category for each product (most specific grouping).
 */
class CategoryMapper
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EavEntityTypeResolver $entityTypeResolver
    ) {
    }

    /**
     * Get primary category name (breadcrumb) for a batch of product IDs
     *
     * Returns: [product_id => 'Women > Tops > Jackets']
     *
     * Logic: For each product, finds the deepest (highest level) assigned category
     * and builds a breadcrumb path. One query for the mapping, one for category names.
     *
     * @param int[] $productIds
     * @param int $storeId
     * @param int $rootCategoryId
     * @return array<int, string>
     */
    public function getProductCategoryMap(array $productIds, int $storeId, int $rootCategoryId): array
    {
        if (empty($productIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();

        // Get deepest category for each product (highest level = most specific)
        $select = $connection->select()
            ->from(
                ['ccp' => $this->resourceConnection->getTableName('catalog_category_product')],
                ['product_id']
            )
            ->join(
                ['cce' => $this->resourceConnection->getTableName('catalog_category_entity')],
                'cce.entity_id = ccp.category_id',
                ['category_id' => 'cce.entity_id', 'level' => 'cce.level', 'path' => 'cce.path']
            )
            ->where('ccp.product_id IN (?)', $productIds)
            ->where('cce.level > ?', 1)
            ->where('cce.path LIKE ?', '%/' . $rootCategoryId . '/%')
            ->order('cce.level DESC');

        $rows = $connection->fetchAll($select);

        // Pick deepest category per product
        $productCategoryIds = [];
        $allCategoryIds = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            if (!isset($productCategoryIds[$productId])) {
                $categoryId = (int) $row['category_id'];
                $productCategoryIds[$productId] = $categoryId;
                $allCategoryIds[$categoryId] = true;

                // Also collect parent IDs from path for breadcrumb
                $pathParts = explode('/', (string) $row['path']);
                foreach ($pathParts as $pathId) {
                    $pathIdInt = (int) $pathId;
                    if ($pathIdInt > $rootCategoryId) {
                        $allCategoryIds[$pathIdInt] = true;
                    }
                }
            }
        }

        if (empty($allCategoryIds)) {
            return [];
        }

        // Load category names (single query for all needed categories)
        $nameSelect = $connection->select()
            ->from(
                ['ccev' => $this->resourceConnection->getTableName('catalog_category_entity_varchar')],
                ['entity_id']
            )
            ->joinLeft(
                ['ccev_store' => $this->resourceConnection->getTableName('catalog_category_entity_varchar')],
                'ccev_store.entity_id = ccev.entity_id'
                . ' AND ccev_store.attribute_id = ccev.attribute_id'
                . ' AND ccev_store.store_id = ' . (int) $storeId,
                []
            )
            ->columns([
                'name' => $connection->getIfNullSql('ccev_store.value', 'ccev.value'),
            ])
            ->where('ccev.entity_id IN (?)', array_keys($allCategoryIds))
            ->where('ccev.store_id = ?', 0)
            ->where('ccev.attribute_id = (SELECT attribute_id FROM '
                . $this->resourceConnection->getTableName('eav_attribute')
                . ' WHERE attribute_code = \'name\' AND entity_type_id = '
                . $this->entityTypeResolver->getCategoryEntityTypeId() . ')');

        $nameRows = $connection->fetchAll($nameSelect);
        $nameMap = [];
        foreach ($nameRows as $nameRow) {
            $nameMap[(int) $nameRow['entity_id']] = (string) $nameRow['name'];
        }

        // Build breadcrumb for each product's primary category
        $result = [];
        foreach ($rows as $row) {
            $productId = (int) $row['product_id'];
            if (!isset($result[$productId]) && isset($productCategoryIds[$productId])) {
                $pathParts = explode('/', (string) $row['path']);
                $breadcrumb = [];
                foreach ($pathParts as $pathId) {
                    $pathIdInt = (int) $pathId;
                    if ($pathIdInt > $rootCategoryId && isset($nameMap[$pathIdInt])) {
                        $breadcrumb[] = $nameMap[$pathIdInt];
                    }
                }
                if (!empty($breadcrumb)) {
                    $result[$productId] = implode(' > ', $breadcrumb);
                }
            }
        }

        return $result;
    }
}
