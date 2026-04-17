<?php

declare(strict_types=1);

namespace RKD\LlmsTxt\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for admin-selectable product attributes
 *
 * Lists all visible, user-defined and system product attributes
 * that have text/select/multiselect values useful for AI output.
 *
 * Excludes: media attributes, internal system attributes, price/sku/name
 * (those are handled separately with dedicated config fields).
 */
class ProductAttributes implements OptionSourceInterface
{
    /**
     * Attributes already handled by dedicated config fields — exclude from multiselect
     */
    private const EXCLUDED_CODES = [
        'name',
        'sku',
        'price',
        'special_price',
        'short_description',
        'description',
        'url_key',
        'status',
        'visibility',
        'image',
        'small_image',
        'thumbnail',
        'swatch_image',
        'media_gallery',
        'gallery',
        'category_ids',
        'tier_price',
        'quantity_and_stock_status',
        'meta_title',
        'meta_keyword',
        'meta_description',
        'url_path',
        'required_options',
        'has_options',
        'options_container',
        'page_layout',
        'custom_layout_update',
        'custom_design',
        'custom_design_from',
        'custom_design_to',
        'custom_layout',
        'msrp',
        'msrp_display_actual_price_type',
        'tax_class_id',
    ];

    /**
     * Backend types that produce useful text output for AI
     */
    private const ALLOWED_BACKEND_TYPES = [
        'varchar',
        'text',
        'int',
        'decimal',
        'static',
    ];

    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        $collection = $this->attributeCollectionFactory->create();
        $collection->addVisibleFilter()
            ->setOrder('frontend_label', 'ASC');

        $options = [];

        foreach ($collection as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            $label = (string) $attribute->getFrontendLabel();
            $backendType = (string) $attribute->getBackendType();

            if ($label === '' || in_array($code, self::EXCLUDED_CODES, true)) {
                continue;
            }

            if (!in_array($backendType, self::ALLOWED_BACKEND_TYPES, true)) {
                continue;
            }

            $options[] = [
                'value' => $code,
                'label' => $label . ' (' . $code . ')',
            ];
        }

        return $options;
    }
}
