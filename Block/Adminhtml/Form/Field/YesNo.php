<?php
namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class YesNo extends Select
{
    /**
     * @var \Magento\Config\Model\Config\Source\Yesno
     */
    protected $sourceConfig;

    public function __construct(
        Context $context,
        \Magento\Config\Model\Config\Source\Yesno $sourceConfig,
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
