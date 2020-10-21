<?php
namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

class Attributes extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider
     */
    protected $dataProvider;

    /**
     * Attributes constructor.
     * @param \Magento\Framework\View\Element\Context $context
     * @param \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider $dataProvider
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider $dataProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dataProvider = $dataProvider;
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
    public function _toHtml()
    {
        if (empty($this->getOptions())) {
            $attributes = $this->dataProvider->getSearchableAttributes();
            foreach ($attributes as $attributeCode => $attribute) {
                // only interested in attribute codes, not ids
                if (is_numeric($attributeCode)) {
                    continue;
                }
                $this->addOption($attributeCode, $attribute->getStoreLabel());
            }
        }

        return parent::_toHtml();
    }

}
