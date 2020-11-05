<?php
namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

class SearchTerm extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    protected function _prepareToRender()
    {
        $this->addColumn(
            'search_term',
            [
                'label' => __('Search Term'),
                'renderer' => false
            ]
        );

        $this->_addAfter = false;
    }
}
