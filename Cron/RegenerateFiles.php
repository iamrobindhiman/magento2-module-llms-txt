<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use RKD\LlmsTxt\Api\LlmsTxtGeneratorInterface;
use RKD\LlmsTxt\Model\Config;
use RKD\LlmsTxt\Model\DirtyFlag;

/**
 * Cron job for scheduled llms.txt regeneration
 *
 * Checks dirty flags before regenerating:
 * - If any section is dirty → regenerate and clear flags
 * - If no section is dirty → skip (no wasted work)
 *
 * Iterates over all stores where auto-regeneration is enabled.
 * Calls the same Generator::generate() that CLI and admin button use.
 */
class RegenerateFiles
{
    public function __construct(
        private readonly LlmsTxtGeneratorInterface $generator,
        private readonly Config $config,
        private readonly DirtyFlag $dirtyFlag,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        // Skip entirely if no section is dirty
        if (!$this->dirtyFlag->isAnyDirty()) {
            $this->logger->debug('RKD LLMs.txt: Cron skipped — no dirty sections');
            return;
        }

        $stores = $this->storeManager->getStores();
        $allSucceeded = true;

        foreach ($stores as $store) {
            $storeId = (int) $store->getId();

            if (!$this->config->isEnabled($storeId) || !$this->config->isAutoRegenerationEnabled($storeId)) {
                continue;
            }

            try {
                $result = $this->generator->generate($storeId, 'cron');

                $this->logger->info('RKD LLMs.txt: Cron regeneration complete', [
                    'store_id' => $storeId,
                    'store_name' => $store->getName(),
                    'status' => $result->getStatus(),
                    'products' => $result->getProductsCount(),
                    'duration' => $result->getDurationSeconds(),
                ]);

                $allSucceeded = $allSucceeded && $result->isSuccess();
            } catch (\Exception $e) {
                $allSucceeded = false;
                $this->logger->error('RKD LLMs.txt: Cron regeneration failed', [
                    'store_id' => $storeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Only clear dirty flags if ALL stores regenerated successfully
        if ($allSucceeded) {
            $this->dirtyFlag->clearAll();
        }
    }
}
