<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Products;

use Aligent\FredhopperExport\Model\Data\GetCurrentExportedVersion;
use Aligent\FredhopperIndexer\Model\ResourceModel\Changelog;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;

class GetCompleteChangeList
{

    // cached result, as we don't want the changelist to change during processing
    /**
     * @var array
     */
    private array $changeList = [];

    /**
     * @param ResourceConnection $resourceConnection
     * @param GetCurrentExportedVersion $getCurrentExportedVersion
     * @param Changelog $changelogResource
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly GetCurrentExportedVersion $getCurrentExportedVersion,
        private readonly Changelog $changelogResource
    ) {
    }

    /**
     * Get complete list of changes since the last export
     *
     * @param string $productType
     * @return array
     * @throws LocalizedException
     */
    public function execute(string $productType): array
    {
        if (!isset($this->changeList[$productType])) {
            $fromVersion = $this->getCurrentExportedVersion->execute();
            $toVersion = $this->changelogResource->getLatestVersionId();
            $this->changeList[$productType] = $this->getChangedProductIds($fromVersion, $toVersion, $productType);
        }
        return $this->changeList[$productType];
    }

    /**
     * Get all added, updated or deleted products between the given versions
     *
     * @throws LocalizedException
     */
    private function getChangedProductIds(int $fromVersionId, int $toVersionId, string $productType): array
    {
        // need to get all changelog records between the given versions
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();
        $select->from(Changelog::TABLE_NAME, ['product_id', 'version_id', 'operation_type']);
        $select->where('product_type = ?', $productType);
        $select->where('version_id > ?', $fromVersionId);
        $select->where('version_id <= ?', $toVersionId);
        $select->order('version_id');
        $rows = $connection->fetchAll($select);

        $data = [];
        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];
            if (array_key_exists($productId, $data)) {
                // need to handle cases where a single product has multiple operations
                $updatedOperation = $this->combineOperations($data[$productId], $row['operation_type']);
                if ($updatedOperation !== null) {
                    $data[$productId] = $updatedOperation;
                } else {
                    unset($data[$productId]);
                }
            } else {
                $data[$productId] = $row['operation_type'];
            }
        }

        $changeList = [
            Changelog::OPERATION_TYPE_ADD => [],
            Changelog::OPERATION_TYPE_UPDATE => [],
            Changelog::OPERATION_TYPE_DELETE => []
        ];

        foreach ($data as $productId => $operation) {
            $changeList[$operation][] = $productId;
        }

        return $changeList;
    }

    /**
     * Reduces two consecutive operations into a single one
     *
     * For example, an add followed by an update is the same as an "add".
     * An "add" followed by a "delete" is the same as nothing happening.
     * An "add" followed by another "add" is invalid
     *
     * @throws LocalizedException
     */
    private function combineOperations(string $operationOne, string $operationTwo): ?string
    {
        $operationResultMap = [
            Changelog::OPERATION_TYPE_ADD => [
                Changelog::OPERATION_TYPE_ADD => false,
                Changelog::OPERATION_TYPE_UPDATE => Changelog::OPERATION_TYPE_ADD,
                Changelog::OPERATION_TYPE_DELETE => null
            ],
            Changelog::OPERATION_TYPE_UPDATE => [
                Changelog::OPERATION_TYPE_ADD => false,
                Changelog::OPERATION_TYPE_UPDATE => Changelog::OPERATION_TYPE_UPDATE,
                Changelog::OPERATION_TYPE_DELETE => Changelog::OPERATION_TYPE_DELETE
            ],
            Changelog::OPERATION_TYPE_DELETE => [
                Changelog::OPERATION_TYPE_ADD => null,
                Changelog::OPERATION_TYPE_UPDATE => false,
                Changelog::OPERATION_TYPE_DELETE => false
            ]
        ];
        if (!isset($operationResultMap[$operationOne][$operationTwo])) {
            throw new LocalizedException(__('Invalid operation'));
        }
        $result = $operationResultMap[$operationOne][$operationTwo];
        if ($result === false) {
            throw new LocalizedException(__('Invalid operation sequence'));
        }
        return $result;
    }
}
