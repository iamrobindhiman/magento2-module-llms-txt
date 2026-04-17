<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use RKD\LlmsTxt\Model\FileWriter;

/**
 * Dashboard block for the admin LLMs.txt Generator page
 */
class Dashboard extends Template
{
    public function __construct(
        Context $context,
        private readonly FileWriter $fileWriter,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get URL for the generate AJAX endpoint
     *
     * @return string
     */
    public function getGenerateUrl(): string
    {
        return $this->getUrl('rkd_llmstxt/generate/index');
    }

    /**
     * Get URL for the preview page
     *
     * @return string
     */
    public function getPreviewUrl(): string
    {
        return $this->getUrl('rkd_llmstxt/preview/index');
    }

    /**
     * Check if llms.txt has been generated
     *
     * @return bool
     */
    public function isGenerated(): bool
    {
        return $this->fileWriter->llmsTxtExists();
    }

    /**
     * Get the frontend URL for llms.txt
     *
     * @return string
     */
    public function getLlmsTxtUrl(): string
    {
        return rtrim($this->_storeManager->getStore()->getBaseUrl(), '/') . '/llms.txt';
    }

    /**
     * Get the frontend URL for llms-full.txt
     *
     * @return string
     */
    public function getFullTxtUrl(): string
    {
        return rtrim($this->_storeManager->getStore()->getBaseUrl(), '/') . '/llms-full.txt';
    }
}
