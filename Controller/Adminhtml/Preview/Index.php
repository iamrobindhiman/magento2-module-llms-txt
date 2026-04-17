<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Controller\Adminhtml\Preview;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;
use RKD\LlmsTxt\Api\LlmsTxtGeneratorInterface;

/**
 * Admin controller: preview llms.txt output without writing files
 *
 * Calls the same Generator::preview() that CLI --dry-run uses.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'RKD_LlmsTxt::preview';

    public function __construct(
        Context $context,
        private readonly LlmsTxtGeneratorInterface $generator,
        private readonly RawFactory $rawFactory,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/plain; charset=utf-8', true);

        try {
            $preview = $this->generator->preview();
            $result->setContents($preview);
        } catch (\Exception $e) {
            $this->logger->error('RKD LLMs.txt: Preview generation failed', [
                'exception' => $e,
            ]);
            $result->setContents('An error occurred while generating the preview. Check var/log/system.log for details.');
        }

        return $result;
    }
}
