<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Changelog;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Magento\Framework\App\ResourceConnection;

class TempTable
{
    public const string TEMP_TABLE_NAME = DataHandler::INDEX_TABLE_NAME . '_temp';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Creates a temporary copy of the index table for use in generating changelog records
     *
     * @return void
     */
    public function create(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->createTemporaryTableLike(self::TEMP_TABLE_NAME, DataHandler::INDEX_TABLE_NAME);
        $copySelect = $connection->select();
        $copySelect->from(DataHandler::INDEX_TABLE_NAME);
        $copyInsert = $connection->insertFromSelect(
            $copySelect,
            self::TEMP_TABLE_NAME
        );
        $connection->query($copyInsert);
    }

    /**
     * Drops the temporary table if it exists
     *
     * @return void
     */
    public function drop(): void
    {
        $this->resourceConnection->getConnection()->dropTemporaryTable(self::TEMP_TABLE_NAME);
    }
}
