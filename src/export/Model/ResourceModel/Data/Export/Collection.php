<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\ResourceModel\Data\Export;

use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
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
