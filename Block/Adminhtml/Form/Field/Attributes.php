<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

use Aligent\FredhopperIndexer\Model\Indexer\Data\AttributeDataProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class Attributes extends Select
{

    /**
     * @param Context $context
     * @param AttributeDataProvider $dataProvider
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly AttributeDataProvider $dataProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param $value
     * @return $this
     */
    public function setInputName($value): Select
    {
        return $this->setData('name', $value);
    }

    /**
     * @return string
     * @throws LocalizedException
     */
    public function _toHtml(): string
    {
        if (empty($this->getOptions())) {
            $attributes = $this->dataProvider->getIndexableAttributes();
            foreach ($attributes as $attributeCode => $attribute) {
                // only interested in attribute codes, not ids
                if (is_numeric($attributeCode)) {
                    continue;
                }
                $this->addOption($attributeCode, implode(' - ', [$attributeCode, $attribute->getStoreLabel()]));
            }
        }

        return parent::_toHtml();
    }
}
