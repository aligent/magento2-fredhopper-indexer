<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Framework\App\ResourceConnection;

class SetCurrentExport
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Sets the export identified by the given ID as the current data set in use
     *
     * @param int $exportId
     * @return void
     */
    public function execute(int $exportId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        $connection->update(
            ExportResource::MAIN_TABLE_NAME,
            ['is_current' => false],
            ['export_id <> ?' => $exportId]
        );
        $connection->update(
            ExportResource::MAIN_TABLE_NAME,
            ['is_current' => true],
            ['export_id = ?' => $exportId]
        );
        $connection->commit();
    }
}
