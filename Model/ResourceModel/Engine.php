<?php


namespace Aligent\FredhopperIndexer\Model\ResourceModel;


class Engine implements \Magento\CatalogSearch\Model\ResourceModel\EngineInterface
{

    /**
     * @var \Magento\Catalog\Model\Product\Visibility
     */
    protected $visibility;

    public function __construct(
        \Magento\Catalog\Model\Product\Visibility $visibility
    ) {
        $this->visibility = $visibility;
    }

    /**
     * @inheritDoc
     */
    public function getAllowedVisibility()
    {
        return $this->visibility->getVisibleInSiteIds();
    }

    /**
     * @inheritDoc
     */
    public function allowAdvancedIndex()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function processAttributeValue($attribute, $value)
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function prepareEntityIndex($index, $separator = ' ')
    {
        return $index;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable()
    {
        return true;
    }
}
