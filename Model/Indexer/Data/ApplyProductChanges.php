<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class ApplyProductChanges
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Insert/update records in main index table from store-level table
     *
     * Also insert into changelog table to ensure correct deltas are sent to Fredhopper
     *
     * @param string $scopeTableName
     * @param int $storeId
     * @return void
     */
    public function execute(string $scopeTableName, int $storeId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $indexTableName = DataHandler::INDEX_TABLE_NAME;

        // insert any new records and mark as "add"
        $insertSelect = $connection->select();
        $insertSelect->from(
            ['scope_table' => $scopeTableName],
            ['product_type', 'product_id', 'parent_id', 'attribute_data']
        );
        $insertSelect->columns(
            [
                'operation_type' => new \Zend_Db_Expr($connection->quote(DataHandler::OPERATION_TYPE_ADD)),
                'store_id' => new \Zend_Db_Expr($storeId)
            ]
        );
        $insertQuery = $connection->insertFromSelect(
            $insertSelect,
            $indexTableName,
            ['product_type', 'product_id', 'parent_id', 'attribute_data', 'operation_type', 'store_id'],
            AdapterInterface::INSERT_IGNORE // ignore mode so only records that do not exist will be inserted
        );
        $connection->query($insertQuery);

        // check for deleted records and mark as "delete"
        $deleteWhereClause = "store_id = $storeId AND NOT EXISTS (SELECT 1 from $scopeTableName scope_table " .
            " WHERE scope_table.product_id = ". $indexTableName . ".product_id " .
            " AND scope_table.product_type = ". $indexTableName . ".product_type)";
        $connection->update(
            $indexTableName,
            ['operation_type' => DataHandler::OPERATION_TYPE_DELETE],
            $deleteWhereClause
        );

        // find records to be updated - where attribute_data or parent_id has changed
        $updateSubSelect = $connection->select();
        $updateSubSelect->from(
            false,
            ['operation_type' => $connection->quote(DataHandler::OPERATION_TYPE_UPDATE)] // used for setting value
        );
        $updateSubSelect->join(
            ['scope_table' => $scopeTableName],
            'main_table.product_id = scope_table.product_id AND main_table.product_type = scope_table.product_type',
            ['attribute_data', 'parent_id']
        );
        $updateSubSelect->where('main_table.store_id = ?', $storeId);
        $updateSubSelect->where('(main_table.attribute_data <> scope_table.attribute_data
            OR NOT main_table.parent_id <=> scope_table.parent_id)');

        $updateQuery = $connection->updateFromSelect(
            $updateSubSelect,
            ['main_table' => $indexTableName]
        );
        $connection->query($updateQuery);

        // Restore incorrectly deleted  products by clearing operation_type
        // Find records that are no longer missing from the scope table but are marked for deletion
        $restoreSubSelect = $connection->select();
        $restoreSubSelect->from(
            false,
            ['operation_type' => 'NULL'] // used for setting value
        );
        $restoreSubSelect->join(
            ['scope_table' => $scopeTableName],
            'main_table.product_id = scope_table.product_id AND main_table.product_type = scope_table.product_type',
            []
        );
        $restoreSubSelect->where('main_table.store_id = ?', $storeId);
        $restoreSubSelect->where('main_table.operation_type = ?', DataHandler::OPERATION_TYPE_DELETE);

        $restoreQuery = $connection->updateFromSelect(
            $restoreSubSelect,
            ['main_table' => $indexTableName]
        );
        $connection->query($restoreQuery);
    }
}
