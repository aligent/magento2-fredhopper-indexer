<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Magento\Framework\App\ResourceConnection;

class IndexReplicaManagement
{
    public const REPLICA_TABLE_NAME = 'fredhopper_product_data_index_replica';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Creates and populates replica of index table
     *
     * @return void
     */
    public function createReplicaTable(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->createTemporaryTableLike(
            self::REPLICA_TABLE_NAME,
            DataHandler::INDEX_TABLE_NAME
        );
        $insertSelect = $connection->select();
        $insertSelect->from(DataHandler::INDEX_TABLE_NAME);
        $insert = $connection->insertFromSelect(
            $insertSelect,
            self::REPLICA_TABLE_NAME
        );
        $connection->query($insert);
    }

    /**
     * Drop the temporary replica table from the database
     *
     * @return void
     */
    public function dropReplicaTable(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->dropTemporaryTable(self::REPLICA_TABLE_NAME);
    }
}
