<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\ResourceModel;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Magento\Framework\App\ResourceConnection;

class Changelog
{

    public const string OPERATION_TYPE_ADD = 'a';
    public const string OPERATION_TYPE_UPDATE = 'u';
    public const string OPERATION_TYPE_DELETE = 'd';

    public const string TABLE_NAME = 'fredhopper_product_changelog';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Insert IDs to be added to Fredhopper exports
     *
     * @param array $productIds
     * @param array $variantIds
     * @return void
     */
    public function insertAdditionOperations(array $productIds, array $variantIds): void
    {
        $this->insertRows($productIds, DataHandler::TYPE_PRODUCT, self::OPERATION_TYPE_ADD);
        $this->insertRows($variantIds, DataHandler::TYPE_VARIANT, self::OPERATION_TYPE_ADD);
    }

    /**
     * Insert UDs to be updated in Fredhopper exports
     *
     * @param array $productIds
     * @param array $variantIds
     * @return void
     */
    public function insertUpdateOperations(array $productIds, array $variantIds): void
    {
        $this->insertRows($productIds, DataHandler::TYPE_PRODUCT, self::OPERATION_TYPE_UPDATE);
        $this->insertRows($variantIds, DataHandler::TYPE_VARIANT, self::OPERATION_TYPE_UPDATE);
    }

    /**
     * Insert IDs to be deleted from Fredhopper exports
     *
     * @param array $productIds
     * @param array $variantIds
     * @return void
     */
    public function insertDeleteOperations(array $productIds, array $variantIds): void
    {
        $this->insertRows($productIds, DataHandler::TYPE_PRODUCT, self::OPERATION_TYPE_DELETE);
        $this->insertRows($variantIds, DataHandler::TYPE_VARIANT, self::OPERATION_TYPE_DELETE);
    }

    /**
     * Insert records into changelog table
     *
     * @param array $productIds
     * @param string $productType
     * @param string $operationType
     * @return void
     */
    private function insertRows(array $productIds, string $productType, string $operationType): void
    {
        if (empty($productIds)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $data = [];
        foreach ($productIds as $productId) {
            $data[] = [
                'product_id' => $productId,
                'product_type' => $productType,
                'operation_type' => $operationType,
            ];
        }
        $connection->insertMultiple(
            $this->resourceConnection->getTableName(self::TABLE_NAME),
            $data
        );
    }

    /**
     * Get the latest version in the table
     *
     * @return int
     */
    public function getLatestVersionId(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();
        $select->from(self::TABLE_NAME, ['version_id']);
        $select->order('version_id DESC');
        $select->limit(1);
        return (int)($connection->fetchOne($select));
    }
}
