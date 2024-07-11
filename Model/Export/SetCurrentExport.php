<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export;

use Aligent\FredhopperIndexer\Model\ResourceModel\Export\Data\Export as ExportResource;
use Magento\Framework\App\ResourceConnection;

class SetCurrentExport
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

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
