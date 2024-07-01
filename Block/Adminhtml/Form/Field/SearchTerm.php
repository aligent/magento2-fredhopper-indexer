<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class SearchTerm extends AbstractFieldArray
{
    protected function _prepareToRender(): void
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
