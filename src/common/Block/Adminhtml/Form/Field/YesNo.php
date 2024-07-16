<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Block\Adminhtml\Form\Field;

use Magento\Config\Model\Config\Source\Yesno as YesNoSource;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class YesNo extends Select
{

    public function __construct(
        readonly Context $context,
        private readonly YesNoSource $sourceConfig,
        readonly array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Sets the name of the input field
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
    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->sourceConfig->toOptionArray());
        }

        return parent::_toHtml();
    }
}
