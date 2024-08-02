<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Products;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Aligent\FredhopperIndexer\Model\ResourceModel\Changelog;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class GetFredhopperProductData
{

    private const string OPERATION_TYPE_ADD = 'add';
    private const string OPERATION_TYPE_UPDATE = 'update';
    private const string OPERATION_TYPE_DELETE = 'delete';

    private const array OPERATION_TYPE_MAPPING = [
        Changelog::OPERATION_TYPE_ADD => self::OPERATION_TYPE_ADD,
        Changelog::OPERATION_TYPE_UPDATE => self::OPERATION_TYPE_UPDATE,
        Changelog::OPERATION_TYPE_DELETE => self::OPERATION_TYPE_DELETE
    ];

    /**
     * @param ResourceConnection $resourceConnection
     * @param GetCompleteChangeList $getCompleteChangeList
     * @param CollateProductData $collateProductData
     * @param ConvertToFredhopperFormat $convertToFredhopperFormat
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly GetCompleteChangeList $getCompleteChangeList,
        private readonly CollateProductData $collateProductData,
        private readonly ConvertToFredhopperFormat $convertToFredhopperFormat
    ) {
    }

    /**
     * Get data in correct format for Fredhopper for the given ids and type
     *
     * @param array $productIds
     * @param string $productType
     * @param bool $isIncremental
     * @return array
     * @throws LocalizedException
     */
    public function execute(array $productIds, string $productType, bool $isIncremental): array
    {
        $rawProductData = $this->getRawProductData($productIds, $productType, $isIncremental);
        return $this->processProductData($rawProductData, $productType);
    }

    /**
     * Get raw product data from index table
     *
     * @param array $productIds
     * @param string $productType
     * @param bool $isIncremental
     * @return array
     * @throws LocalizedException
     */
    private function getRawProductData(array $productIds, string $productType, bool $isIncremental): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();
        $select->from(
            DataHandler::INDEX_TABLE_NAME,
            [
                'store_id' => 'store_id',
                'product_type' => 'product_type',
                'product_id' => 'product_id',
                'parent_id' => 'parent_id',
                'attribute_data' => 'attribute_data'
            ]
        );
        $select->where('product_type = ?', $productType);
        $select->where('product_id IN (?)', $productIds);
        $data = $connection->fetchAll($select);

        if (!$isIncremental) {
            $data = $this->addOperationsToData($data, $productType);
        }
        return $data;
    }

    /**
     * Add operations to raw product data
     *
     * @throws LocalizedException
     */
    private function addOperationsToData(array $productData, string $productType): array
    {
        $changeSet = $this->getCompleteChangeList->execute($productType);
        foreach ($productData as $key => $data) {
            $productId = (int)$data['product_id'];
            $productData[$key]['operation'] = self::OPERATION_TYPE_MAPPING[$changeSet[$productId]];
        }
        return $productData;
    }

    /**
     * Process raw product data into the required format
     *
     * @param array $rawProductData
     * @param string $productType
     * @return array
     * @throws LocalizedException
     */
    private function processProductData(array $rawProductData, string $productType): array
    {
        $productStoreData = $this->collateProductData->execute($rawProductData);
        return $this->convertToFredhopperFormat->execute($productStoreData, $productType);
    }
}
