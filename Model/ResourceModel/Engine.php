<?php

declare(strict_types=1);

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
    public function getAllowedVisibility(): array
    {
        return $this->visibility->getVisibleInSiteIds();
    }

    /**
     * @inheritDoc
     */
    public function allowAdvancedIndex(): bool
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
    public function prepareEntityIndex($index, $separator = ' '): array
    {
        return $index;
    }

    public function isAvailable(): bool
    {
        return true;
    }
}
