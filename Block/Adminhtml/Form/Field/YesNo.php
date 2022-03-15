<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

use Magento\Config\Model\Config\Source\Yesno as YesNoSource;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class YesNo extends Select
{

    private YesNoSource $sourceConfig;

    public function __construct(
        Context $context,
        YesNoSource $sourceConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->sourceConfig = $sourceConfig;
    }

    /**
     * @param $value
     * @return $this
     * @throws LocalizedException
     */
    public function setInputName($value): Select
    {
        return $this->setName($value);
    }

    /**
     * @return string
     */
    protected function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->sourceConfig->toOptionArray());
        }

        return parent::_toHtml();
    }
}
