<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Changelog;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Aligent\FredhopperIndexer\Model\ResourceModel\Changelog as ChangelogResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class InsertRecords
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param ChangelogResource $changelogResource
     * @param TempTable $tempTable
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ChangelogResource $changelogResource,
        private readonly TempTable $tempTable
    ) {
    }

    /**
     * Insert add, update and delete records into the changelog table
     *
     * @return void
     * @throws \Zend_Db_Select_Exception
     * @throws LocalizedException
     */
    public function execute(): void
    {
        $tempTableName = $this->tempTable->getTempTableName();
        $addedProductIds = $this->getAddedOrDeletedProductsByType(
            true,
            DataHandler::TYPE_PRODUCT,
            $tempTableName
        );
        $addedVariantIds = $this->getAddedOrDeletedProductsByType(
            true,
            DataHandler::TYPE_VARIANT,
            $tempTableName
        );
        $this->changelogResource->insertAdditionOperations($addedProductIds, $addedVariantIds);

        $updatedProductIds = $this->getUpdatedProductsByType(DataHandler::TYPE_PRODUCT, $tempTableName);
        $updatedVariantIds = $this->getUpdatedProductsByType(DataHandler::TYPE_VARIANT, $tempTableName);
        $this->changelogResource->insertUpdateOperations($updatedProductIds, $updatedVariantIds);

        $deletedProductIds = $this->getAddedOrDeletedProductsByType(
            false,
            DataHandler::TYPE_PRODUCT,
            $tempTableName
        );
        $deletedVariantIds = $this->getAddedOrDeletedProductsByType(
            false,
            DataHandler::TYPE_VARIANT,
            $tempTableName
        );
        $this->changelogResource->insertDeleteOperations($deletedProductIds, $deletedVariantIds);
    }

    /**
     * Determine added or deleted product ids by finding records in one table that are not in the other
     *
     * @param bool $isAddition
     * @param string $productType
     * @param string $tempTableName
     * @return array
     */
    private function getAddedOrDeletedProductsByType(
        bool $isAddition,
        string $productType,
        string $tempTableName
    ): array {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();

        $select->from(
            ['main_table' => ($isAddition ? DataHandler::INDEX_TABLE_NAME : $tempTableName)],
            ['product_id']
        );
        $select->joinLeft(
            ['temp_table' => ($isAddition ? $tempTableName : DataHandler::INDEX_TABLE_NAME)],
            'temp_table.product_id = main_table.product_id AND '.
            'temp_table.product_type = main_table.product_type',
            []
        );
        $select->where('temp_table.product_id is null');
        $select->where('main_table.product_type = ?', $productType);
        $select->group('main_table.product_id');

        return $connection->fetchCol($select);
    }

    /**
     * Determine which products have been updated between the main and temporary table
     *
     * @param string $productType
     * @param string $tempTableName
     * @return array
     * @throws \Zend_Db_Select_Exception
     */
    private function getUpdatedProductsByType(string $productType, string $tempTableName): array
    {
        // get all product ids and variant ids that exist in both tables
        // we do not want to consider products that are being added or deleted completely
        $connection = $this->resourceConnection->getConnection();
        $existingProductsSelect = $connection->select();
        $existingProductsSelect->from(['temp_table' => $tempTableName], ['product_id']);
        $existingProductsSelect->joinInner(
            ['main_table' => DataHandler::INDEX_TABLE_NAME],
            'main_table.product_id = temp_table.product_id AND ' .
            'main_table.product_type = temp_table.product_type',
            []
        );
        $existingProductsSelect->distinct();
        $existingProductsSelect->where('temp_table.product_type = ?', $productType);
        $existingProductIds = $connection->fetchCol($existingProductsSelect);

        // records that are in the new table, but not in the old table
        $existingProductsTempMissingSelect = $connection->select();
        $existingProductsTempMissingSelect->from(
            ['main_table' => DataHandler::INDEX_TABLE_NAME],
            ['product_id']
        );
        $existingProductsTempMissingSelect->joinLeft(
            ['temp_table' => $tempTableName],
            'main_table.product_id = temp_table.product_id AND ' .
            'main_table.product_type = temp_table.product_type AND ' .
            'main_table.store_id = temp_table.store_id',
            []
        );
        $existingProductsTempMissingSelect->where('temp_table.product_id IS NULL');
        $existingProductsTempMissingSelect->where('main_table.product_type = ?', $productType);
        $existingProductsTempMissingSelect->where('main_table.product_id in (?)', $existingProductIds);

        // records that are in the old table, but not in the new table
        $existingProductsMainMissingSelect = $connection->select();
        $existingProductsMainMissingSelect->from(
            ['temp_table' => $tempTableName],
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

        // records that differ by parent_id or attribute_data
        $existingProductsDifferenceSelect = $connection->select();
        $existingProductsDifferenceSelect->from(
            ['main_table' => DataHandler::INDEX_TABLE_NAME],
            ['product_id']
        );
        $existingProductsDifferenceSelect->joinInner(
            ['temp_table' => $tempTableName],
            'main_table.product_id = temp_table.product_id AND ' .
            'main_table.product_type = temp_table.product_type AND ' .
            'main_table.store_id = temp_table.store_id AND '.
            'NOT (main_table.parent_id <=> temp_table.parent_id AND ' .
            'main_table.attribute_data <=> temp_table.attribute_data)',
            []
        );
        $existingProductsDifferenceSelect->where('main_table.product_type = ?', $productType);
        $existingProductsDifferenceSelect->where('main_table.product_id in (?)', $existingProductIds);

        $updatedProductsSelect = $connection->select()->union(
            [
                $existingProductsMainMissingSelect,
                $existingProductsTempMissingSelect,
                $existingProductsDifferenceSelect
            ]
        );
        return $connection->fetchCol($updatedProductsSelect);
    }
}
