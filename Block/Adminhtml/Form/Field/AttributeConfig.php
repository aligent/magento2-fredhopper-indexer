<?php
namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

class AttributeConfig extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    protected $attributeRenderer;
    protected $fhAttributeTypeRenderer;
    protected $appendSiteVariantRenderer;

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
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
     * @return \Magento\Framework\View\Element\BlockInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getAttributeRenderer()
    {
        if (!$this->attributeRenderer) {
            $this->attributeRenderer = $this->getLayout()->createBlock(
                \Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field\Attributes::class,
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
     * @return \Magento\Framework\View\Element\BlockInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getFHAttributeTypeRenderer()
    {
        if (!$this->fhAttributeTypeRenderer) {
            $this->fhAttributeTypeRenderer = $this->getLayout()->createBlock(
                \Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field\FHAttributeTypes::class,
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
     * @return \Magento\Framework\View\Element\BlockInterface|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getAppendSiteVariantRenderer()
    {
        if (!$this->appendSiteVariantRenderer) {
            $this->appendSiteVariantRenderer = $this->getLayout()->createBlock(
                \Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field\YesNo::class,
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
     * @param \Magento\Framework\DataObject $row
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $options['option_' . $this->getAttributeRenderer()->calcOptionHash($row->getData('attribute'))]
            = 'selected="selected"';

        $options['option_' . $this->getFHAttributeTypeRenderer()->calcOptionHash($row->getData('fredhopper_type'))]
            = 'selected="selected"';

        $options['option_' . $this->getAppendSiteVariantRenderer()->calcOptionHash($row->getData('append_site_variant'))]
            = 'selected="selected"';

        $row->setData('option_extra_attrs', $options);
    }
}
