<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Html\Select;

class FHAttributeTypes extends Select
{
    public const string ATTRIBUTE_TYPE_INT = 'int';
    public const string ATTRIBUTE_TYPE_FLOAT = 'float';
    public const string ATTRIBUTE_TYPE_LIST = 'list';
    public const string ATTRIBUTE_TYPE_LIST64 = 'list64';
    public const string ATTRIBUTE_TYPE_SET = 'set';
    public const string ATTRIBUTE_TYPE_SET64 = 'set64';
    public const string ATTRIBUTE_TYPE_TEXT = 'text';
    public const string ATTRIBUTE_TYPE_ASSET = 'asset';
    public const string ATTRIBUTE_TYPE_HIERARCHICAL = 'hierarchical'; // only for categories

    private const array ATTRIBUTE_TYPES = [
        self::ATTRIBUTE_TYPE_INT,
        self::ATTRIBUTE_TYPE_FLOAT,
        self::ATTRIBUTE_TYPE_LIST,
        self::ATTRIBUTE_TYPE_LIST64,
        self::ATTRIBUTE_TYPE_SET,
        self::ATTRIBUTE_TYPE_SET64,
        self::ATTRIBUTE_TYPE_TEXT,
        self::ATTRIBUTE_TYPE_ASSET
    ];

    /**
     * Set input name of field
     *
     * @param $value
     * @return $this
     */
    public function setInputName($value): Select
    {
        return $this->setData('name', $value);
    }

    /**
     * @inheritDoc
     */
    public function _toHtml(): string
    {
        if (empty($this->getOptions())) {
            foreach (self::ATTRIBUTE_TYPES as $attributeType) {
                $this->addOption($attributeType, $attributeType);
            }
        }

        return parent::_toHtml();
    }
}
