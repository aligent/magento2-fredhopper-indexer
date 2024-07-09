<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class AttributeNameMapping extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'magento_attribute_code',
            [
                'label' => __('Magento Attribute Code'),
                'renderer' => null
            ]
        );

        $this->addColumn(
            'fredhopper_attribute_id',
            [
                'label' => __('FH Attribute ID'),
                'renderer' => null
            ]
        );

        $this->_addAfter = false;
    }
}
