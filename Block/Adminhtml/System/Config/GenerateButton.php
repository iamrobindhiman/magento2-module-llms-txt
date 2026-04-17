<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * "Generate Now" button rendered in admin system config page
 *
 * Triggers an AJAX call to the admin Generate controller,
 * which calls the same Generator::generate() used by CLI and Cron.
 */
class GenerateButton extends Field
{
    /**
     * @var string
     */
    protected $_template = 'RKD_LlmsTxt::system/config/generate_button.phtml';

    /**
     * Remove the default scope label and element rendering
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
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
}
