<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Centralized config reader for all RKD_LlmsTxt system.xml paths
 */
class Config
{
    private const XML_PATH_PREFIX = 'rkd_llmstxt/';

    // General
    private const XML_PATH_ENABLED = 'general/enabled';
    private const XML_PATH_AUTO_REGENERATION = 'general/auto_regeneration';
    private const XML_PATH_CRON_SCHEDULE = 'general/cron_schedule';
    private const XML_PATH_STORE_DESCRIPTION = 'general/store_description';

    // Content
    private const XML_PATH_INCLUDE_CMS_PAGES = 'content/include_cms_pages';
    private const XML_PATH_INCLUDE_CATEGORIES = 'content/include_categories';
    private const XML_PATH_CATEGORY_DEPTH_LIMIT = 'content/category_depth_limit';
    private const XML_PATH_INCLUDE_PRODUCTS = 'content/include_products';
    private const XML_PATH_PRODUCT_LIMIT = 'content/product_limit';
    private const XML_PATH_PRODUCT_SORT_ORDER = 'content/product_sort_order';
    private const XML_PATH_INCLUDE_METADATA = 'content/include_metadata';
    private const XML_PATH_EXCLUDE_OUT_OF_STOCK = 'content/exclude_out_of_stock';

    // llms-full.txt
    private const XML_PATH_FULL_TXT_ENABLED = 'full_txt/enabled';
    private const XML_PATH_FULL_TXT_MAX_SIZE = 'full_txt/max_file_size_mb';
    private const XML_PATH_FULL_TXT_DEPTH = 'full_txt/content_depth';

    // Product Data
    private const XML_PATH_INCLUDE_PRICE = 'product_data/include_price';
    private const XML_PATH_INCLUDE_SKU = 'product_data/include_sku';
    private const XML_PATH_INCLUDE_SHORT_DESC = 'product_data/include_short_description';
    private const XML_PATH_EXTRA_ATTRIBUTES = 'product_data/extra_attributes';

    // Validation
    private const XML_PATH_VALIDATE_ON_GEN = 'validation/validate_on_generation';
    private const XML_PATH_BLOCK_ON_ERRORS = 'validation/block_on_errors';

    // Robots.txt
    private const XML_PATH_ROBOTS_AUTO_INJECT = 'robots_txt/auto_inject';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isAutoRegenerationEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_AUTO_REGENERATION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCronSchedule(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . self::XML_PATH_CRON_SCHEDULE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isIncludeCmsPages(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_INCLUDE_CMS_PAGES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isIncludeCategories(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_INCLUDE_CATEGORIES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getCategoryDepthLimit(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . self::XML_PATH_CATEGORY_DEPTH_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isIncludeProducts(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_INCLUDE_PRODUCTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductLimit(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . self::XML_PATH_PRODUCT_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProductSortOrder(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . self::XML_PATH_PRODUCT_SORT_ORDER,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isIncludeMetadata(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_INCLUDE_METADATA,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isExcludeOutOfStock(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_EXCLUDE_OUT_OF_STOCK,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isFullTxtEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_FULL_TXT_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getFullTxtMaxSizeMb(?int $storeId = null): int
    {
        return (int) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . self::XML_PATH_FULL_TXT_MAX_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getContentDepth(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . self::XML_PATH_FULL_TXT_DEPTH,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isIncludePrice(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_INCLUDE_PRICE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isIncludeSku(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_INCLUDE_SKU,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isIncludeShortDescription(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_INCLUDE_SHORT_DESC,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isValidateOnGeneration(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_VALIDATE_ON_GEN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isBlockOnErrors(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_BLOCK_ON_ERRORS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isRobotsAutoInject(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PREFIX . self::XML_PATH_ROBOTS_AUTO_INJECT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get the underlying ScopeConfigInterface for direct access
     *
     * @return ScopeConfigInterface
     */
    public function getScopeConfig(): ScopeConfigInterface
    {
        return $this->scopeConfig;
    }

    public function getStoreDescription(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . self::XML_PATH_STORE_DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get admin-selected extra product attribute codes
     *
     * @param int|null $storeId
     * @return string[] Array of attribute codes
     */
    public function getExtraAttributes(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PREFIX . self::XML_PATH_EXTRA_ATTRIBUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($value === '') {
            return [];
        }

        return explode(',', $value);
    }

    /**
     * Get store currency code
     *
     * @param int|null $storeId
     * @return string
     */
    public function getStoreCurrency(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'currency/options/base',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get product URL suffix from Magento catalog SEO config
     *
     * @param int|null $storeId
     * @return string e.g. ".html", ".htm", "" (empty for no suffix)
     */
    public function getProductUrlSuffix(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'catalog/seo/product_url_suffix',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get category URL suffix from Magento catalog SEO config
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCategoryUrlSuffix(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'catalog/seo/category_url_suffix',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
