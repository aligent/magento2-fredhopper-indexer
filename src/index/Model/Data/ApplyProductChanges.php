<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Data;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Aligent\FredhopperIndexer\Model\ResourceModel\Changelog;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Psr\Log\LoggerInterface;

class ApplyProductChanges
{
    private const string TEMP_TABLE_NAME = DataHandler::INDEX_TABLE_NAME . '_temp';

    /**
     * @param ResourceConnection $resourceConnection
     * @param Changelog $changelogResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Changelog $changelogResource,
        private readonly LoggerInterface $logger
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
        $this->createTempTable($storeId);
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $this->applyScopeChanges($scopeTableName, $storeId, $connection);
            $this->insertChangelogRecords($connection, $storeId);
            $connection->commit();
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
        $this->dropTempTable();
    }

    /**
     * Create temporary copy of index table
     *
     * @param int $storeId
     * @return void
     */
    private function createTempTable(int $storeId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->createTemporaryTableLike(self::TEMP_TABLE_NAME, DataHandler::INDEX_TABLE_NAME);
        $copySelect = $connection->select();
        $copySelect->from(DataHandler::INDEX_TABLE_NAME);
        $copySelect->where('store_id = ?', $storeId);
        $copyInsert = $connection->insertFromSelect(
            $copySelect,
            self::TEMP_TABLE_NAME
        );
        $connection->query($copyInsert);
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

    /**
     * Insert into changelog table based on differences
     *
     * @param AdapterInterface $connection
     * @param int $storeId
     * @return void
     * @throws \Zend_Db_Select_Exception
     */
    private function insertChangelogRecords(AdapterInterface $connection, int $storeId): void
    {

        $addedProductIds = $this->getAddedOrDeletedProductsByType(
            true,
            DataHandler::TYPE_PRODUCT,
            $storeId,
            $connection
        );
        $addedVariantIds = $this->getAddedOrDeletedProductsByType(
            true,
            DataHandler::TYPE_VARIANT,
            $storeId,
            $connection
        );
        $this->changelogResource->insertAdditionOperations($addedProductIds, $addedVariantIds);

        $updatedProductIds = $this->getUpdatedProductsByType(DataHandler::TYPE_PRODUCT, $storeId, $connection);
        $updatedVariantIds = $this->getUpdatedProductsByType(DataHandler::TYPE_VARIANT, $storeId, $connection);
        $this->changelogResource->insertUpdateOperations($updatedProductIds, $updatedVariantIds);

        $deletedProductIds = $this->getAddedOrDeletedProductsByType(
            false,
            DataHandler::TYPE_PRODUCT,
            $storeId,
            $connection
        );
        $deletedVariantIds = $this->getAddedOrDeletedProductsByType(
            false,
            DataHandler::TYPE_VARIANT,
            $storeId,
            $connection
        );
        $this->changelogResource->insertDeleteOperations($deletedProductIds, $deletedVariantIds);

    }

    /**
     * Determine added or deleted product ids by finding records in one table that are not in the other
     *
     * @param bool $isAddition
     * @param string $productType
     * @param int $storeId
     * @param AdapterInterface $connection
     * @return array
     */
    private function getAddedOrDeletedProductsByType(
        bool $isAddition,
        string $productType,
        int $storeId,
        AdapterInterface $connection): array
    {
        $select = $connection->select();

        $select->from(
            ['main_table' => ($isAddition ? DataHandler::INDEX_TABLE_NAME : self::TEMP_TABLE_NAME)],
            ['product_id']
        );
        $select->joinLeft(
            ['temp_table' => ($isAddition ? self::TEMP_TABLE_NAME : DataHandler::INDEX_TABLE_NAME)],
            'temp_table.product_id = main_table.product_id AND '.
            'temp_table.product_type = main_table.product_type AND '.
            'temp_table.store_id = main_table.store_id',
            []
        );
        $select->where('temp_table.product_id is null');
        $select->where('main_table.product_type = ?', $productType);
        $select->where('main_table.store_id = ?', $storeId);
        $select->group('main_table.product_id');

        return $connection->fetchCol($select);
    }

    /**
     * Determine which products have been updated between the main and temporary table
     *
     * @param string $productType
     * @param int $storeId
     * @param AdapterInterface $connection
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    private function getUpdatedProductsByType(string $productType, int $storeId, AdapterInterface $connection): array
    {
        // get all product ids and variant ids that exist in both tables
        // we do not want to consider products that are being added or deleted completely
        $existingProductsSelect = $connection->select();
        $existingProductsSelect->from(['temp_table' => self::TEMP_TABLE_NAME], ['product_id']);
        $existingProductsSelect->joinInner(
            ['main_table' => DataHandler::INDEX_TABLE_NAME],
            'main_table.product_id = temp_table.product_id AND ' .
            'main_table.product_type = temp_table.product_type AND ' .
            'temp_table.store_id = main_table.store_id',
            []
        );
        $existingProductsSelect->distinct();
        $existingProductsSelect->where('temp_table.product_type = ?', $productType);
        $existingProductsSelect->where('temp_table.store_id = ?', $storeId);
        $existingProductIds = $connection->fetchCol($existingProductsSelect);

        // records that are in the new table, but not in the old table
        $existingProductsTempMissingSelect = $connection->select();
        $existingProductsTempMissingSelect->from(
            ['main_table' => DataHandler::INDEX_TABLE_NAME],
            ['product_id']
        );
        $existingProductsTempMissingSelect->joinLeft(
            ['temp_table' => self::TEMP_TABLE_NAME],
            'main_table.product_id = temp_table.product_id AND ' .
            'main_table.product_type = temp_table.product_type AND ' .
            'main_table.store_id = temp_table.store_id',
            []
        );
        $existingProductsTempMissingSelect->where('temp_table.product_id IS NULL');
        $existingProductsTempMissingSelect->where('main_table.product_type = ?', $productType);
        $existingProductsTempMissingSelect->where('main_table.product_id in (?)', $existingProductIds);
        $existingProductsTempMissingSelect->where('main_table.store_id = ?', $storeId);

        // records that are in the old table, but not in the new table
        $existingProductsMainMissingSelect = $connection->select();
        $existingProductsMainMissingSelect->from(
            ['temp_table' => self::TEMP_TABLE_NAME],
            ['product_id']
        );
        $existingProductsMainMissingSelect->joinLeft(
            ['main_table' => DataHandler::INDEX_TABLE_NAME],
            'main_table.product_id = temp_table.product_id AND ' .
            'main_table.product_type = temp_table.product_type AND ' .
            'main_table.store_id = temp_table.store_id',
            []
        );
        $existingProductsMainMissingSelect->where('main_table.product_id IS NULL');
        $existingProductsMainMissingSelect->where('temp_table.product_type = ?', $productType);
        $existingProductsMainMissingSelect->where('temp_table.product_id in (?)', $existingProductIds);
        $existingProductsMainMissingSelect->where('temp_table.store_id = ?', $storeId);

        // records that differ by parent_id or attribute_data
        $existingProductsDifferenceSelect = $connection->select();
        $existingProductsDifferenceSelect->from(
            ['main_table' => DataHandler::INDEX_TABLE_NAME],
            ['product_id']
        );
        $existingProductsDifferenceSelect->joinInner(
            ['temp_table' => self::TEMP_TABLE_NAME],
            'main_table.product_id = temp_table.product_id AND ' .
            'main_table.product_type = temp_table.product_type AND ' .
            'main_table.store_id = temp_table.store_id AND '.
            'NOT (main_table.parent_id <=> temp_table.parent_id AND ' .
            'main_table.attribute_data <=> temp_table.attribute_data)',
            []
        );
        $existingProductsDifferenceSelect->where('main_table.product_type = ?', $productType);
        $existingProductsDifferenceSelect->where('main_table.product_id in (?)', $existingProductIds);
        $existingProductsDifferenceSelect->where('main_table.store_id = ?', $storeId);

        $updatedProductsSelect = $connection->select()->union(
            [
                $existingProductsMainMissingSelect,
                $existingProductsTempMissingSelect,
                $existingProductsDifferenceSelect
            ]
        );
        return $connection->fetchCol($updatedProductsSelect);
    }

    /**
     * Drop temporary table
     *
     * @return void
     */
    private function dropTempTable(): void
    {
        $this->resourceConnection->getConnection()->dropTemporaryTable(self::TEMP_TABLE_NAME);
    }
}
