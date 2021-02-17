<?php
namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

class YesNo extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Magento\Config\Model\Config\Source\Yesno
     */
    protected $sourceConfig;

    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Config\Model\Config\Source\Yesno $sourceConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->sourceConfig = $sourceConfig;
    }

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
    protected function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->sourceConfig->toOptionArray());
        }

        return parent::_toHtml();
    }
}