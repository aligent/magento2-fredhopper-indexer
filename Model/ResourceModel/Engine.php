<?php


namespace Aligent\FredhopperIndexer\Model\ResourceModel;

use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogSearch\Model\ResourceModel\EngineInterface;

class Engine implements EngineInterface
{

    /**
     * @var Visibility
     */
    protected $visibility;

    public function __construct(
        Visibility $visibility
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
