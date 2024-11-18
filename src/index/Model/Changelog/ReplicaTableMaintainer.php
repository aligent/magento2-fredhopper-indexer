<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Changelog;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Random\RandomException;

class ReplicaTableMaintainer
{

    public const REPLICA_TABLE_NAME = DataHandler::INDEX_TABLE_NAME . '_replica';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Generate a random string of characters to use as an ID when comparing records before/after indexing
     *
     * @return string
     */
    public function generateUniqueId(): string
    {
        try {
            $id = bin2hex(random_bytes(8));
        } catch (RandomException) {
            $id = uniqid( '',true);
        }
        return substr($id, 0, 8);
    }

    /**
     * Insert records from the main index table into the replica table using the given ID
     *
     * @param string $id
     * @return void
     */
    public function insertRecords(string $id): void
    {
        $connection = $this->resourceConnection->getConnection();
        $copySelect = $connection->select();
        $selectColumns = [
            'replica_id' => new \Zend_Db_Expr("'$id'"),
            'store_id' => 'store_id',
            'product_type' => 'product_type',
            'product_id' => 'product_id',
            'parent_id' => 'parent_id',
            'attribute_data' => 'attribute_data',

        ];
        $copySelect->from(
            DataHandler::INDEX_TABLE_NAME,
            $selectColumns
        );

        $copyInsert = $connection->insertFromSelect(
            $copySelect,
            self::REPLICA_TABLE_NAME
        );
        $connection->query($copyInsert);
    }

    /**
     * Delete records from the replica table with the given ID
     *
     * @param string $id
     * @return void
     */
    public function deleteRecords(string $id): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete(
            self::REPLICA_TABLE_NAME,
            ['replica_id = ?' => $id]
        );
    }
}
