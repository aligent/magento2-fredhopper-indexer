<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Html\Select;

class AttributeConfig extends AbstractFieldArray
{

    /**
     * @var Select
     */
    private Select $attributeRenderer;
    /**
     * @var Select
     */
    private Select $fhAttributeTypeRenderer;
    /**
     * @var Select
     */
    private Select $appendSiteVariantRenderer;

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'attribute',
            [
                'label' => __('Attribute'),
                'renderer' => $this->getAttributeRenderer()
            ]
        );

        $this->addColumn(
            'fredhopper_type',
            [
                'label' => __('FH Attribute Type'),
                'renderer' => $this->getFHAttributeTypeRenderer()
            ]
        );

        $this->addColumn(
            'append_site_variant',
            [
                'label' => __('Append Site Variant?'),
                'renderer' => $this->getAppendSiteVariantRenderer()
            ]
        );

        $this->_addAfter = false;
    }

    /**
     * @return Select
     * @throws LocalizedException
     */
    private function getAttributeRenderer(): Select
    {
        if (!isset($this->attributeRenderer)) {
            $this->attributeRenderer = $this->getLayout()->createBlock(
                Attributes::class,
                '',
                [
                    'data' => [
                        'is_render_to_js_template' => true
                    ]
                ]
            );
        }

        return $this->attributeRenderer;
    }

    /**
     * @return Select
     * @throws LocalizedException
     */
    private function getFHAttributeTypeRenderer(): Select
    {
        if (!isset($this->fhAttributeTypeRenderer)) {
            $this->fhAttributeTypeRenderer = $this->getLayout()->createBlock(
                FHAttributeTypes::class,
                '',
                [
                    'data' => [
                        'is_render_to_js_template' => true
                    ]
                ]
            );
        }

        return $this->fhAttributeTypeRenderer;
    }

    /**
     * @return Select
     * @throws LocalizedException
     */
    private function getAppendSiteVariantRenderer(): Select
    {
        if (!isset($this->appendSiteVariantRenderer)) {
            $this->appendSiteVariantRenderer = $this->getLayout()->createBlock(
                YesNo::class,
                '',
                [
                    'data' => [
                        'is_render_to_js_template' => true
                    ]
                ]
            );
        }

        return $this->appendSiteVariantRenderer;
    }

    /**
     * @param DataObject $row
     * @throws LocalizedException
     */
    protected function _prepareArrayRow(DataObject $row): void
    {
        $attributeRendererHash = $this->getAttributeRenderer()->calcOptionHash($row->getData('attribute'));
        $fhAttributeTypeRendererHash = $this->getFHAttributeTypeRenderer()->calcOptionHash(
            $row->getData('fredhopper_type')
        );
        $appendSiteVariantRendererHash = $this->getAppendSiteVariantRenderer()->calcOptionHash(
            $row->getData('append_site_variant')
        );

        $options['option_' . $attributeRendererHash] = 'selected="selected"';
        $options['option_' . $fhAttributeTypeRendererHash]  = 'selected="selected"';
        $options['option_' . $appendSiteVariantRendererHash] = 'selected="selected"';

        $row->setData('option_extra_attrs', $options);
    }
}
