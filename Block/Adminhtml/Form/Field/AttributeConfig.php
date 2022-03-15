<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Html\Select;

class AttributeConfig extends AbstractFieldArray
{

    private Select $attributeRenderer;
    private Select $fhAttributeTypeRenderer;
    private Select $appendSiteVariantRenderer;

    /**
     * @throws LocalizedException
     */
    protected function _prepareToRender()
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
    protected function getAttributeRenderer(): Select
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
    protected function getFHAttributeTypeRenderer(): Select
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
    protected function getAppendSiteVariantRenderer(): Select
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
    protected function _prepareArrayRow(DataObject $row)
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
