<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\HttpFactory as ResponseFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use RKD\LlmsTxt\Model\FileWriter;

/**
 * Serves /llms.txt and /llms-full.txt as text/plain responses
 *
 * No generation logic — reads the pre-generated file from pub/ via FileWriter.
 */
class Serve implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseFactory $responseFactory,
        private readonly FileWriter $fileWriter
    ) {
    }

    /**
     * @return ResultInterface|ResponseInterface
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $fileType = $this->request->getParam('file_type', 'llms_txt');

        $content = $fileType === 'llms_full_txt'
            ? $this->fileWriter->readFullTxt()
            : $this->fileWriter->readLlmsTxt();

        if ($content === '') {
            $response = $this->responseFactory->create();
            $response->setStatusCode(404);
            $response->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
            $response->setBody(
                'File not generated yet. Run: bin/magento rkd:llmstxt:generate'
            );

            return $response;
        }

        $response = $this->responseFactory->create();
        $response->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $response->setHeader('X-Robots-Tag', 'noindex', true);
        $response->setBody($content);

        return $response;
    }
}
