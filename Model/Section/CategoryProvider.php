<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Section;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use RKD\LlmsTxt\Api\Data\SectionInterface;
use RKD\LlmsTxt\Api\Data\SectionInterfaceFactory;
use RKD\LlmsTxt\Api\SectionProviderInterface;
use RKD\LlmsTxt\Model\Config;
use RKD\LlmsTxt\Model\EavEntityTypeResolver;
use RKD\LlmsTxt\Model\TextSanitizer;

/**
 * Provides category tree section for llms.txt
 *
 * Performance: Single collection query with depth filter at SQL level.
 *
 * BUG-04: Uses breadcrumb paths instead of space indentation.
 * NEW-02: Breadcrumbs make duplicate names distinguishable (Men > Tops vs Women > Tops).
 * NEW-03: Uses real category descriptions or product counts instead of "Browse X products".
 */
class CategoryProvider implements SectionProviderInterface
{
    /**
     * Magento root category level
     */
    private const ROOT_LEVEL = 1;

    public function __construct(
        private readonly SectionInterfaceFactory $sectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
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
        return 'Product Categories';
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return 30;
    }

    /**
     * @inheritdoc
     */
    public function getSection(int $storeId): SectionInterface
    {
        $store = $this->storeManager->getStore($storeId);
        $baseUrl = rtrim($store->getBaseUrl(), '/');
        $rootCategoryId = (int) $store->getRootCategoryId();
        $depthLimit = $this->config->getCategoryDepthLimit($storeId);

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'url_path', 'description', 'is_active', 'level'])
            ->addIsActiveFilter()
            ->addFieldToFilter('level', ['gt' => self::ROOT_LEVEL])
            ->addFieldToFilter('path', ['like' => '%/' . $rootCategoryId . '/%'])
            ->setOrder('level', 'ASC')
            ->setOrder('position', 'ASC');

        if ($depthLimit > 0) {
            $maxLevel = self::ROOT_LEVEL + 1 + $depthLimit;
            $collection->addFieldToFilter('level', ['lteq' => $maxLevel]);
        }

        // Build ID → name map for breadcrumb lookups
        $nameMap = [];
        $parentMap = [];
        $categoryIds = [];
        foreach ($collection as $category) {
            $catId = (int) $category->getId();
            $categoryIds[] = $catId;
            $nameMap[$catId] = $this->sanitizer->sanitize((string) $category->getData('name'));
            $pathParts = explode('/', (string) $category->getData('path'));
            if (count($pathParts) >= 2) {
                $parentMap[$catId] = (int) $pathParts[count($pathParts) - 2];
            }
        }

        // M2 fix: Batch-load product counts (single query, not EAV attribute)
        $productCounts = $this->batchLoadProductCounts($categoryIds);

        $links = [];
        $fullContentParts = [];

        foreach ($collection as $category) {
            $catId = (int) $category->getId();
            $name = $nameMap[$catId] ?? '';
            $urlPath = (string) $category->getData('url_path');
            $description = (string) $category->getData('description');
            $level = (int) $category->getData('level');
            $productCount = $productCounts[$catId] ?? 0;

            if ($name === '' || $urlPath === '') {
                continue;
            }

            $url = $baseUrl . '/' . $urlPath . $this->config->getCategoryUrlSuffix($storeId);

            // BUG-04 + NEW-02: Build breadcrumb path instead of indentation
            $breadcrumb = $this->buildBreadcrumb($catId, $nameMap, $parentMap, $rootCategoryId);

            // llms.txt: breadcrumb with product count
            $linkLabel = $productCount > 0
                ? sprintf('%s (%d products)', $breadcrumb, $productCount)
                : $breadcrumb;
            $links[$linkLabel] = $url;

            // NEW-03: Use real description or product count, not "Browse X products"
            $descriptionText = '';
            if ($description !== '') {
                $descriptionText = $this->sanitizer->stripHtml($description);
            }
            if ($descriptionText === '' && $productCount > 0) {
                $descriptionText = sprintf('%d products available', $productCount);
            }

            $fullContentParts[] = sprintf(
                "### %s\n\nURL: %s\n%s",
                $breadcrumb,
                $url,
                $descriptionText
            );
        }

        $count = count($links);
        $summary = sprintf('Product category hierarchy (%d categories)', $count);

        $section = $this->sectionFactory->create();
        $section->setName($this->getSectionName())
            ->setPriority($this->getPriority())
            ->setSummary($summary)
            ->setLinks($links)
            ->setFullContent(implode("\n\n", $fullContentParts))
            ->setItemCount($count);

        return $section;
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(int $storeId): bool
    {
        return $this->config->isEnabled($storeId) && $this->config->isIncludeCategories($storeId);
    }

    /**
     * Build breadcrumb path for a category: "Women > Tops > Jackets"
     *
     * Walks up the parent chain using the path field.
     * Skips root (id=1) and default category (id=2).
     *
     * @param int $categoryId
     * @param array<int, string> $nameMap
     * @param array<int, int> $parentMap
     * @param int $rootCategoryId
     * @return string
     */
    private function buildBreadcrumb(int $categoryId, array $nameMap, array $parentMap, int $rootCategoryId): string
    {
        $parts = [];
        $currentId = $categoryId;
        $maxDepth = 20; // L4 fix: guard against infinite loop from corrupted path data
        $depth = 0;

        while (isset($nameMap[$currentId]) && $depth++ < $maxDepth) {
            $parts[] = $nameMap[$currentId];
            $parentId = $parentMap[$currentId] ?? 0;

            if ($parentId <= $rootCategoryId) {
                break;
            }
            $currentId = $parentId;
        }

        return implode(' > ', array_reverse($parts));
    }

    /**
     * Batch-load VISIBLE product counts per category
     *
     * Joins catalog_category_product with product visibility to exclude
     * invisible simple variants (children of configurable products).
     *
     * Without this filter, a configurable product with 15 color/size variants
     * would count as 16 products (1 parent + 15 children), inflating counts
     * by 10-16x on stores with many configurable products.
     *
     * @param int[] $categoryIds
     * @return array<int, int>
     */
    private function batchLoadProductCounts(array $categoryIds): array
    {
        if (empty($categoryIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $eavTable = $this->resourceConnection->getTableName('eav_attribute');
        $productTypeId = $this->entityTypeResolver->getProductEntityTypeId();
        $visibilityAttrId = (int) $connection->fetchOne(
            $connection->select()
                ->from($eavTable, ['attribute_id'])
                ->where('attribute_code = ?', 'visibility')
                ->where('entity_type_id = ?', $productTypeId)
        );

        $statusAttrId = (int) $connection->fetchOne(
            $connection->select()
                ->from($eavTable, ['attribute_id'])
                ->where('attribute_code = ?', 'status')
                ->where('entity_type_id = ?', $productTypeId)
        );

        $select = $connection->select()
            ->from(
                ['ccp' => $this->resourceConnection->getTableName('catalog_category_product')],
                ['category_id', 'count' => new \Magento\Framework\DB\Sql\Expression('COUNT(*)')]
            )
            ->join(
                ['cpei_vis' => $this->resourceConnection->getTableName('catalog_product_entity_int')],
                'cpei_vis.entity_id = ccp.product_id'
                . ' AND cpei_vis.attribute_id = ' . $visibilityAttrId
                . ' AND cpei_vis.store_id = 0'
                . ' AND cpei_vis.value IN (2, 3, 4)',  // Catalog, Search, or Both — NOT "Not Visible Individually"
                []
            )
            ->join(
                ['cpei_status' => $this->resourceConnection->getTableName('catalog_product_entity_int')],
                'cpei_status.entity_id = ccp.product_id'
                . ' AND cpei_status.attribute_id = ' . $statusAttrId
                . ' AND cpei_status.store_id = 0'
                . ' AND cpei_status.value = 1',  // Enabled only
                []
            )
            ->where('ccp.category_id IN (?)', $categoryIds)
            ->group('ccp.category_id');

        $rows = $connection->fetchAll($select);

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['category_id']] = (int) $row['count'];
        }

        return $counts;
    }
}
