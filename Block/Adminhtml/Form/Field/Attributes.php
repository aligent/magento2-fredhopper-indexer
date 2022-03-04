<?php
namespace Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field;

use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

class Attributes extends Select
{
    /**
     * @var DataProvider
     */
    protected $dataProvider;

    /**
     * Attributes constructor.
     * @param Context $context
     * @param DataProvider $dataProvider
     * @param array $data
     */
    public function __construct(
        Context $context,
        DataProvider $dataProvider,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->dataProvider = $dataProvider;
    }

    /**
     * @param $value
     * @return $this
     * @throws LocalizedException
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
