<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Data;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Psr\Log\LoggerInterface;

class ApplyProductChanges
{

    /**
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Insert/update records in main index table from store-level table
     *
     * @param string $scopeTableName
     * @param int $storeId
     * @return void
     */
    public function execute(string $scopeTableName, int $storeId): void
    {
        $connection = $this->resourceConnection->getConnection();
        try {
            $this->applyScopeChanges($scopeTableName, $storeId, $connection);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Apply changes from scope table to main index table
     *
     * @param string $scopeTableName
     * @param int $storeId
     * @param AdapterInterface $connection
     * @return void
     */
    private function applyScopeChanges(string $scopeTableName, int $storeId, AdapterInterface $connection): void
    {
        $connection->beginTransaction();
        $connection->delete(
            DataHandler::INDEX_TABLE_NAME,
            ['store_id = ?' => $storeId]
        );

        // insert records from the scope table
        $insertSelect = $connection->select();
        $insertSelect->from(
            ['scope_table' => $scopeTableName],
            ['product_type', 'product_id', 'parent_id', 'attribute_data']
        );
        $insertSelect->columns(
            [
                'store_id' => new \Zend_Db_Expr($storeId)
            ]
        );
        $insertQuery = $connection->insertFromSelect(
            $insertSelect,
            DataHandler::INDEX_TABLE_NAME,
            ['product_type', 'product_id', 'parent_id', 'attribute_data', 'store_id']
        );
        $connection->query($insertQuery);
        $connection->commit();
    }
}
