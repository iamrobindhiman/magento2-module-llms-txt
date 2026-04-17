<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * Admin dashboard page for LLMs.txt Generator
 *
 * GET page with generate button, preview, and last generation info.
 * This is the page the menu item "Generate Files" links to.
 */
class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'RKD_LlmsTxt::generate';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('RKD_LlmsTxt::generate');
        $resultPage->getConfig()->getTitle()->prepend(__('LLMs.txt Generator'));

        return $resultPage;
    }
}
