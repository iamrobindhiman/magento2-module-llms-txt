<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Section;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use RKD\LlmsTxt\Api\Data\SectionInterface;
use RKD\LlmsTxt\Api\Data\SectionInterfaceFactory;
use RKD\LlmsTxt\Api\SectionProviderInterface;
use RKD\LlmsTxt\Model\Config;
use RKD\LlmsTxt\Model\TextSanitizer;

/**
 * Provides store metadata section (store name, base URL, description)
 *
 * Pulls store name from Magento config (general/store_information/name)
 * with fallback to store view name.
 *
 * FIX-05: Uses real store name, not "Default Store View"
 * NEW-01: fullContent no longer generates its own H1 heading
 */
class MetadataProvider implements SectionProviderInterface
{
    public function __construct(
        private readonly SectionInterfaceFactory $sectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config,
        private readonly TextSanitizer $sanitizer
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getSectionName(): string
    {
        return 'Store Information';
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * @inheritdoc
     */
    public function getSection(int $storeId): SectionInterface
    {
        $store = $this->storeManager->getStore($storeId);
        $storeName = $this->getStoreName($storeId);
        $baseUrl = rtrim($store->getBaseUrl(), '/');
        $currency = $this->config->getStoreCurrency($storeId);

        // Use custom store description or generate one
        $storeDescription = $this->getStoreDescription($storeId);
        $summary = $storeDescription !== ''
            ? $storeDescription
            : sprintf('%s is an online store. Visit us at %s', $storeName, $baseUrl);

        $fullContent = sprintf(
            "Website: %s\nCurrency: %s\n\n%s",
            $baseUrl,
            $currency,
            $summary
        );

        $section = $this->sectionFactory->create();
        $section->setName($this->getSectionName())
            ->setPriority($this->getPriority())
            ->setSummary($summary)
            ->setLinks([$storeName => $baseUrl])
            ->setFullContent($fullContent)
            ->setItemCount(1);

        return $section;
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(int $storeId): bool
    {
        return $this->config->isEnabled($storeId) && $this->config->isIncludeMetadata($storeId);
    }

    /**
     * Get store name from Magento config with fallback
     *
     * Priority: general/store_information/name → store view name → 'This Store'
     *
     * @param int $storeId
     * @return string
     */
    public function getStoreName(int $storeId): string
    {
        // FIX-05: Pull from Stores > Config > General > Store Information > Store Name
        $configName = $this->scopeConfig->getValue(
            'general/store_information/name',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!empty($configName)) {
            return $this->sanitizer->sanitize((string) $configName);
        }

        $storeName = $this->storeManager->getStore($storeId)->getName();

        return $this->sanitizer->sanitize((string) $storeName);
    }

    /**
     * Get store description with fallback chain
     *
     * Priority:
     * 1. Custom field: rkd_llmstxt/general/store_description (merchant-written)
     * 2. Empty string (caller handles auto-generation from catalog stats)
     *
     * @param int $storeId
     * @return string
     */
    public function getStoreDescription(int $storeId): string
    {
        $custom = $this->config->getStoreDescription($storeId);
        if ($custom !== '') {
            return $this->sanitizer->sanitize($custom);
        }

        return '';
    }
}
