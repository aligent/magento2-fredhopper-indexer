<?php
namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

class FHAttributeTypes extends \Magento\Framework\View\Element\Html\Select
{
    public const ATTRIBUTE_TYPE_INT = 'int';
    public const ATTRIBUTE_TYPE_FLOAT = 'float';
    public const ATTRIBUTE_TYPE_LIST = 'list';
    public const ATTRIBUTE_TYPE_LIST64 = 'list64';
    public const ATTRIBUTE_TYPE_SET = 'set';
    public const ATTRIBUTE_TYPE_SET64 = 'set64';
    public const ATTRIBUTE_TYPE_TEXT = 'text';
    public const ATTRIBUTE_TYPE_ASSET = 'asset';
    public const ATTRIBUTE_TYPE_HIERARCHICAL = 'hierarchical'; // only for categories

    protected const ATTRIBUTE_TYPES = [
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
     * @param $value
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * @return string
     */
    public function _toHtml()
    {
        if (empty($this->getOptions())) {
            foreach (self::ATTRIBUTE_TYPES as $attributeType) {
                $this->addOption($attributeType, $attributeType);
            }
        }

        return parent::_toHtml();
    }
}
