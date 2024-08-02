<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\ResourceModel\Data;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Export extends AbstractDb
{

    public const string MAIN_TABLE_NAME = 'aligent_fredhopper_export';

    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(self::MAIN_TABLE_NAME, ExportInterface::FIELD_EXPORT_ID);
    }
}
