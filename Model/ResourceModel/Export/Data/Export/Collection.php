<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\ResourceModel\Export\Data\Export;

use Aligent\FredhopperIndexer\Model\Export\Data\Export;
use Aligent\FredhopperIndexer\Model\ResourceModel\Export\Data\Export as ExportResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(Export::class, ExportResource::class);
    }
}
