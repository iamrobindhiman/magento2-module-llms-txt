<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Plugin;

use Magento\Robots\Model\Robots;
use Magento\Store\Model\StoreManagerInterface;
use RKD\LlmsTxt\Model\Config;
use RKD\LlmsTxt\Model\FileWriter;

/**
 * Appends llms.txt and llms-full.txt references to robots.txt
 *
 * After-plugin on Magento's Robots model. Appends lines only when:
 * - Module is enabled
 * - Auto-inject config is on
 * - The files actually exist in pub/
 *
 * No generation logic here — reads config and checks file existence only.
 */
class RobotsTxtPlugin
{
    public function __construct(
        private readonly Config $config,
        private readonly FileWriter $fileWriter,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Append llms.txt references after robots.txt data is generated
     *
     * @param Robots $subject
     * @param string $result
     * @return string
     */
    /**
     * @param Robots $subject
     * @param string|null $result
     * @return string
     */
    public function afterGetData(Robots $subject, ?string $result): string
    {
        $result = $result ?? '';
        $storeId = (int) $this->storeManager->getStore()->getId();

        if (!$this->config->isEnabled($storeId) || !$this->config->isRobotsAutoInject($storeId)) {
            return $result;
        }

        $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        $additions = [];

        // Use comment-based references — llms.txt files are not sitemaps
        if ($this->fileWriter->llmsTxtExists()) {
            $additions[] = '';
            $additions[] = '# LLMs.txt - AI Product Discovery';
            $additions[] = '# https://llmstxt.org/';
            $additions[] = sprintf('# LLMs.txt: %s/llms.txt', $baseUrl);
        }

        if ($this->fileWriter->fullTxtExists()) {
            $additions[] = sprintf('# LLMs-full.txt: %s/llms-full.txt', $baseUrl);
        }

        if (!empty($additions)) {
            $result .= implode("\n", $additions) . "\n";
        }

        return $result;
    }
}
