<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Section;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use RKD\LlmsTxt\Api\Data\SectionInterface;
use RKD\LlmsTxt\Api\Data\SectionInterfaceFactory;
use RKD\LlmsTxt\Api\SectionProviderInterface;
use RKD\LlmsTxt\Model\Config;
use RKD\LlmsTxt\Model\TextSanitizer;

/**
 * Provides CMS pages section for llms.txt
 *
 * Performance: Single collection query with store filter and active filter.
 *
 * FIX-06: Only excludes system pages (no-route, home, enable-cookies).
 *         Privacy policy and other content pages are now included.
 * BUG-01/02: All text sanitized via TextSanitizer.
 * NEW-04: CMS content cleaned via htmlToMarkdown().
 */
class CmsPageProvider implements SectionProviderInterface
{
    /**
     * System pages to exclude — these are not useful content for LLMs
     */
    private const EXCLUDED_IDENTIFIERS = [
        'no-route',
        'home',
        'enable-cookies',
    ];

    public function __construct(
        private readonly SectionInterfaceFactory $sectionFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly TextSanitizer $sanitizer
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getSectionName(): string
    {
        return 'CMS Pages';
    }

    /**
     * @inheritdoc
     */
    public function getPriority(): int
    {
        return 20;
    }

    /**
     * @inheritdoc
     */
    public function getSection(int $storeId): SectionInterface
    {
        $baseUrl = rtrim($this->storeManager->getStore($storeId)->getBaseUrl(), '/');

        $collection = $this->pageCollectionFactory->create();
        $collection->addStoreFilter($storeId)
            ->addFieldToSelect(['title', 'identifier', 'content_heading', 'content'])
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('identifier', ['nin' => self::EXCLUDED_IDENTIFIERS])
            ->setOrder('title', 'ASC');

        $links = [];
        $fullContentParts = [];

        foreach ($collection as $page) {
            $title = $this->sanitizer->sanitize((string) $page->getData('title'));
            $identifier = (string) $page->getData('identifier');
            $url = $baseUrl . '/' . $identifier;

            if ($title === '') {
                continue;
            }

            $links[$title] = $url;

            $content = (string) $page->getData('content');
            $cleanContent = $this->sanitizer->htmlToMarkdown($content);

            $fullContentParts[] = sprintf(
                "### %s\n\nURL: %s\n\n%s",
                $title,
                $url,
                $cleanContent
            );
        }

        $count = count($links);
        $summary = sprintf('Information pages and policies (%d pages)', $count);

        $section = $this->sectionFactory->create();
        $section->setName($this->getSectionName())
            ->setPriority($this->getPriority())
            ->setSummary($summary)
            ->setLinks($links)
            ->setFullContent(implode("\n\n---\n\n", $fullContentParts))
            ->setItemCount($count);

        return $section;
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(int $storeId): bool
    {
        return $this->config->isEnabled($storeId) && $this->config->isIncludeCmsPages($storeId);
    }
}
