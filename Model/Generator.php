<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use RKD\LlmsTxt\Api\Data\GenerationResultInterface;
use RKD\LlmsTxt\Api\Data\GenerationResultInterfaceFactory;
use RKD\LlmsTxt\Api\Data\SectionInterface;
use RKD\LlmsTxt\Api\LlmsTxtGeneratorInterface;
use RKD\LlmsTxt\Api\SectionProviderInterface;

/**
 * Core generation orchestrator
 *
 * Collects content from registered SectionProviders, assembles llms.txt
 * and llms-full.txt following the specification, validates, and writes files.
 */
class Generator implements LlmsTxtGeneratorInterface
{
    /**
     * @param GenerationResultInterfaceFactory $resultFactory
     * @param Config $config
     * @param Validator $validator
     * @param FileWriter $fileWriter
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     * @param SectionProviderInterface[] $sectionProviders
     */
    public function __construct(
        private readonly GenerationResultInterfaceFactory $resultFactory,
        private readonly Config $config,
        private readonly Validator $validator,
        private readonly FileWriter $fileWriter,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly TextSanitizer $sanitizer,
        private readonly array $sectionProviders = []
    ) {
    }

    /**
     * @inheritdoc
     */
    public function generate(?int $storeId = null, string $trigger = 'manual'): GenerationResultInterface
    {
        $startTime = microtime(true);
        $storeId = $this->resolveStoreId($storeId);

        /** @var GenerationResultInterface $result */
        $result = $this->resultFactory->create();

        if (!$this->config->isEnabled($storeId)) {
            $result->setSuccess(false)
                ->setStatus('error')
                ->setValidationErrors(['Module is disabled for this store']);
            return $result;
        }

        $sections = $this->collectSections($storeId);
        $totalProducts = $this->countProducts($sections);

        // Build llms.txt (summary + links)
        $llmsTxtContent = $this->buildLlmsTxt($storeId, $sections);

        // Validate
        $validationErrors = [];
        if ($this->config->isValidateOnGeneration($storeId)) {
            $validationErrors = $this->validator->validate($llmsTxtContent);
        }

        // Block write if validation errors and config says so
        if (!empty($validationErrors) && $this->config->isBlockOnErrors($storeId)) {
            $duration = microtime(true) - $startTime;
            $result->setSuccess(false)
                ->setFileType('none')
                ->setSectionsCount(count($sections))
                ->setProductsCount($totalProducts)
                ->setFileSizeBytes(0)
                ->setDurationSeconds((float) $duration)
                ->setValidationErrors($validationErrors)
                ->setStatus('error');

            $this->logger->warning('RKD LLMs.txt: Generation blocked due to validation errors', [
                'store_id' => $storeId,
                'errors' => $validationErrors,
            ]);

            return $result;
        }

        // Collect non-blocking warnings from providers (e.g. over-limit notice)
        foreach ($sections as $section) {
            foreach ($section->getWarnings() as $warning) {
                $validationErrors[] = $warning;
            }
        }

        // Write llms.txt
        $totalSize = $this->fileWriter->writeLlmsTxt($llmsTxtContent, $storeId);
        $fileType = 'llms_txt';

        // Build and write llms-full.txt if enabled
        if ($this->config->isFullTxtEnabled($storeId)) {
            $fullTxtContent = $this->buildFullTxt($storeId, $sections);
            $maxSizeMb = $this->config->getFullTxtMaxSizeMb($storeId);
            $maxSizeBytes = $maxSizeMb * 1024 * 1024;

            if (strlen($fullTxtContent) <= $maxSizeBytes) {
                $totalSize += $this->fileWriter->writeFullTxt($fullTxtContent, $storeId);
                $fileType = 'both';
            } else {
                $validationErrors[] = sprintf(
                    'llms-full.txt exceeds max size (%d MB). Skipped.',
                    $maxSizeMb
                );
                $this->logger->warning('RKD LLMs.txt: llms-full.txt exceeds size limit', [
                    'store_id' => $storeId,
                    'size_bytes' => strlen($fullTxtContent),
                    'max_bytes' => $maxSizeBytes,
                ]);
            }
        }

        $duration = microtime(true) - $startTime;

        $this->logger->info('RKD LLMs.txt: Generation complete', [
            'store_id' => $storeId,
            'trigger' => $trigger,
            'sections' => count($sections),
            'products' => $totalProducts,
            'size_bytes' => $totalSize,
            'duration_seconds' => round($duration, 2),
        ]);

        $result->setSuccess(true)
            ->setFileType($fileType)
            ->setSectionsCount(count($sections))
            ->setProductsCount($totalProducts)
            ->setFileSizeBytes($totalSize)
            ->setDurationSeconds((float) $duration)
            ->setValidationErrors($validationErrors)
            ->setStatus(empty($validationErrors) ? 'success' : 'partial');

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function preview(?int $storeId = null): string
    {
        $storeId = $this->resolveStoreId($storeId);
        $sections = $this->collectSections($storeId);
        return $this->buildLlmsTxt($storeId, $sections);
    }

    /**
     * @inheritdoc
     */
    public function validate(?int $storeId = null): array
    {
        if (!$this->fileWriter->llmsTxtExists()) {
            return ['llms.txt file does not exist. Run generation first.'];
        }

        $content = $this->fileWriter->readLlmsTxt();
        return $this->validator->validate($content);
    }

    /**
     * Collect sections from all enabled providers, sorted by priority
     *
     * @return SectionInterface[]
     */
    private function collectSections(int $storeId): array
    {
        $sections = [];

        foreach ($this->sectionProviders as $providerName => $provider) {
            if (!$provider->isEnabled($storeId)) {
                continue;
            }

            try {
                $section = $provider->getSection($storeId);
                if ($section->getItemCount() > 0 || $section->getSummary() !== '') {
                    $sections[] = $section;
                }
            } catch (\Exception $e) {
                $this->logger->error('RKD LLMs.txt: Section provider failed', [
                    'provider' => $providerName,
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
                // Continue with remaining providers — partial output is better than none
            }
        }

        // Sort by priority (lower number = higher in file)
        usort($sections, static function (SectionInterface $a, SectionInterface $b): int {
            return $a->getPriority() <=> $b->getPriority();
        });

        return $sections;
    }

    /**
     * Build llms.txt content: summary + links per section
     *
     * Format follows https://llmstxt.org/ spec:
     * # Title
     * > Description
     * ## Section
     * - [Link](URL): Description
     *
     * @param SectionInterface[] $sections
     */
    private function buildLlmsTxt(int $storeId, array $sections): string
    {
        $storeName = $this->getStoreName($storeId);
        $totalProducts = $this->countProducts($sections);
        $totalCategories = $this->countBySection($sections, 'Product Categories');

        $lines = [];
        $lines[] = '# ' . $storeName;
        $lines[] = '';

        // FIX-05: Dynamic description with real catalog stats
        $descParts = [$storeName];
        if ($totalProducts > 0) {
            $descParts[] = sprintf('offers %d products', $totalProducts);
        }
        if ($totalCategories > 0) {
            $descParts[] = sprintf('across %d categories', $totalCategories);
        }
        $lines[] = '> ' . implode(' ', $descParts) . '.';
        $lines[] = '';

        foreach ($sections as $section) {
            $lines[] = '## ' . $section->getName();
            $lines[] = '';

            $summary = $section->getSummary();
            if ($summary !== '') {
                $lines[] = $summary;
                $lines[] = '';
            }

            foreach ($section->getLinks() as $title => $url) {
                // Category sub-headings from ProductProvider (### prefix)
                if (str_starts_with($title, '### ')) {
                    $lines[] = '';
                    $lines[] = $title;
                    $lines[] = '';
                    continue;
                }
                $lines[] = sprintf('- [%s](%s)', $title, $url);
            }

            $lines[] = '';
        }

        $alternates = $this->buildLanguageAlternatesSection($storeId);
        if ($alternates !== '') {
            $lines[] = $alternates;
        }

        $lines[] = $this->buildMetadataFooter($storeId, $sections);

        return implode("\n", $lines);
    }

    /**
     * Build llms-full.txt content: complete content per section
     *
     * NEW-01 FIX: Only ONE H1 heading in the entire document.
     *
     * @param SectionInterface[] $sections
     */
    private function buildFullTxt(int $storeId, array $sections): string
    {
        $storeName = $this->getStoreName($storeId);
        $totalProducts = $this->countProducts($sections);
        $totalCategories = $this->countBySection($sections, 'Product Categories');

        $lines = [];
        $lines[] = '# ' . $storeName . ' — Complete Catalog';
        $lines[] = '';
        $lines[] = sprintf(
            '> Complete product catalog and store content for %s. %d products across %d categories.',
            $storeName,
            $totalProducts,
            $totalCategories
        );
        $lines[] = '';

        foreach ($sections as $section) {
            $lines[] = '## ' . $section->getName();
            $lines[] = '';

            $fullContent = $section->getFullContent();
            if ($fullContent !== '') {
                $lines[] = $fullContent;
                $lines[] = '';
            }
        }

        $alternates = $this->buildLanguageAlternatesSection($storeId);
        if ($alternates !== '') {
            $lines[] = $alternates;
        }

        $lines[] = $this->buildMetadataFooter($storeId, $sections);

        return implode("\n", $lines);
    }

    /**
     * Build "Available in Other Languages" section linking to sibling store views on the same domain
     *
     * Discovery aid for AI crawlers: an AI fetching one language's llms.txt can find the others.
     * Includes sibling stores where the module is enabled AND share the same base URL host
     * (catches both single-website-multi-language and multi-website-same-domain topologies;
     * excludes cross-domain stores which would be a different brand/site).
     * Returns empty string when there are no other enabled stores to link to.
     */
    private function buildLanguageAlternatesSection(int $currentStoreId): string
    {
        try {
            $currentStore = $this->storeManager->getStore($currentStoreId);
            $currentHost = parse_url((string) $currentStore->getBaseUrl(), PHP_URL_HOST);
        } catch (\Exception $e) {
            return '';
        }

        $alternates = [];
        foreach ($this->storeManager->getStores() as $store) {
            $siblingId = (int) $store->getId();
            if ($siblingId === $currentStoreId) {
                continue;
            }
            // Skip inactive store views — they aren't reachable by AI crawlers
            if (!$store->getIsActive()) {
                continue;
            }
            // Skip cross-domain stores (different brand/site) — only link same-host siblings
            $siblingHost = parse_url((string) $store->getBaseUrl(), PHP_URL_HOST);
            if ($siblingHost !== $currentHost) {
                continue;
            }
            if (!$this->config->isEnabled($siblingId)) {
                continue;
            }
            $baseUrl = rtrim($store->getBaseUrl(), '/');
            $name = $this->sanitizer->sanitize((string) $store->getName());
            $alternates[$name] = $baseUrl . '/llms.txt';
        }

        if (empty($alternates)) {
            return '';
        }

        $lines = [];
        $lines[] = '## Available in Other Languages';
        $lines[] = '';
        $lines[] = 'This store is also available in other language versions. Each language has its own AI-readable catalog:';
        $lines[] = '';
        foreach ($alternates as $name => $url) {
            $lines[] = sprintf('- [%s](%s)', $name, $url);
        }
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Build metadata footer with generation timestamp, version, and counts
     *
     * @param int $storeId
     * @param SectionInterface[] $sections
     * @return string
     */
    private function buildMetadataFooter(int $storeId, array $sections): string
    {
        $currency = $this->config->getStoreCurrency($storeId);
        $totalProducts = $this->countProducts($sections);
        $totalCategories = $this->countBySection($sections, 'Product Categories');
        $totalCmsPages = $this->countBySection($sections, 'CMS Pages');

        $lines = [];
        $lines[] = '---';
        $lines[] = '';
        $lines[] = '## Metadata';
        $lines[] = '';
        $lines[] = sprintf('- Generated: %s UTC', gmdate('Y-m-d H:i:s'));
        $lines[] = '- Generator: RKD LLMs.txt v1.0.0';
        $lines[] = sprintf('- Currency: %s', $currency);
        $lines[] = sprintf(
            '- Products: %d | Categories: %d | CMS Pages: %d',
            $totalProducts,
            $totalCategories,
            $totalCmsPages
        );
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Get store name from config or fallback to store view name
     *
     * FIX-05: Uses the MetadataProvider's getStoreName() logic —
     * checks general/store_information/name first.
     */
    private function getStoreName(int $storeId): string
    {
        // Check Magento's store information config first
        $configName = $this->config->getScopeConfig()->getValue(
            'general/store_information/name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if (!empty($configName)) {
            return $this->sanitizer->sanitize((string) $configName);
        }

        return $this->sanitizer->sanitize(
            (string) $this->storeManager->getStore($storeId)->getName()
        );
    }

    /**
     * Get item count from a section by name
     */
    private function countBySection(array $sections, string $sectionName): int
    {
        foreach ($sections as $section) {
            if ($section->getName() === $sectionName) {
                return $section->getItemCount();
            }
        }
        return 0;
    }

    private function resolveStoreId(?int $storeId): int
    {
        if ($storeId !== null) {
            return $storeId;
        }

        return (int) $this->storeManager->getStore()->getId();
    }

    /**
     * @param SectionInterface[] $sections
     */
    private function countProducts(array $sections): int
    {
        foreach ($sections as $section) {
            if ($section->getName() === 'Products') {
                return $section->getItemCount();
            }
        }
        return 0;
    }
}
