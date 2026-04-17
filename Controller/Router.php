<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Controller;

use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use RKD\LlmsTxt\Model\Config;

/**
 * Custom router to handle /llms.txt and /llms-full.txt URLs
 *
 * Intercepts these paths and forwards to our Serve controller.
 * All file serving goes through the controller (not static files)
 * so we control headers, encoding, and future analytics.
 */
class Router implements RouterInterface
{
    private const ROUTE_MAP = [
        'llms.txt' => 'llms_txt',
        'llms-full.txt' => 'llms_full_txt',
    ];

    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Config $config
    ) {
    }

    /**
     * Match /llms.txt or /llms-full.txt and forward to our controller
     *
     * @param RequestInterface $request
     * @return ActionInterface|null
     */
    public function match(RequestInterface $request): ?ActionInterface
    {
        // Prevent infinite loop: if already routed to our module, don't match again
        if ($request->getModuleName() === 'rkd_llmstxt') {
            return null;
        }

        $pathInfo = trim((string) $request->getPathInfo(), '/');

        if (!isset(self::ROUTE_MAP[$pathInfo])) {
            return null;
        }

        if (!$this->config->isEnabled()) {
            return null;
        }

        $request->setModuleName('rkd_llmstxt')
            ->setControllerName('index')
            ->setActionName('serve')
            ->setParam('file_type', self::ROUTE_MAP[$pathInfo])
            ->setAlias(
                \Magento\Framework\Url::REWRITE_REQUEST_PATH_ALIAS,
                $pathInfo
            );

        return $this->actionFactory->create(Forward::class);
    }
}
