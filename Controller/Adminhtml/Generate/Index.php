<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Controller\Adminhtml\Generate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;
use RKD\LlmsTxt\Api\LlmsTxtGeneratorInterface;
use RKD\LlmsTxt\Model\DirtyFlag;

/**
 * Admin controller: triggers generation via the same Generator::generate()
 * that CLI and Cron use. Returns JSON result for the admin button AJAX call.
 */
class Index extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'RKD_LlmsTxt::generate';

    public function __construct(
        Context $context,
        private readonly LlmsTxtGeneratorInterface $generator,
        private readonly DirtyFlag $dirtyFlag,
        private readonly JsonFactory $jsonFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        try {
            $genResult = $this->generator->generate(null, 'manual');

            // Clear dirty flags after manual generation
            $this->dirtyFlag->clearAll();

            $data = [
                'success' => $genResult->isSuccess(),
                'sections_count' => $genResult->getSectionsCount(),
                'products_count' => $genResult->getProductsCount(),
                'file_size' => $this->formatBytes($genResult->getFileSizeBytes()),
                'duration' => round($genResult->getDurationSeconds(), 2),
                'status' => $genResult->getStatus(),
                'validation_errors' => $genResult->getValidationErrors(),
            ];

            if (!$genResult->isSuccess()) {
                $data['message'] = implode('; ', $genResult->getValidationErrors());
            }

            return $result->setData($data);
        } catch (\Exception $e) {
            $this->logger->error('RKD LLMs.txt: Admin generation failed', [
                'exception' => $e,
            ]);
            return $result->setData([
                'success' => false,
                'message' => 'Generation failed. Check var/log/system.log for details.',
            ]);
        }
    }

    /**
     * Format bytes to human-readable string
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }
}
