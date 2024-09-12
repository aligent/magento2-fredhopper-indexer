<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Products;

use Aligent\FredhopperExport\Model\Data\GetCurrentExportedVersion;
use Aligent\FredhopperIndexer\Model\ResourceModel\Changelog;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class GetCompleteChangeList
{

    // cached results, as we don't want the changelist to change during processing
    /**
     * @var array
     */
    private array $changeList = [];

    /**
     * @var array
     */
    private array $changeListByProductId = [];

    /**
     * @param ResourceConnection $resourceConnection
     * @param GetCurrentExportedVersion $getCurrentExportedVersion
     * @param Changelog $changelogResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly GetCurrentExportedVersion $getCurrentExportedVersion,
        private readonly Changelog $changelogResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get complete list of changes since the last export
     *
     * @param string $productType
     * @return array
     */
    public function getList(string $productType): array
    {
        if (!isset($this->changeList[$productType])) {
            $changListByProductId = $this->getListByProductId($productType);
            $changeList = [
                Changelog::OPERATION_TYPE_ADD => [],
                Changelog::OPERATION_TYPE_UPDATE => [],
                Changelog::OPERATION_TYPE_DELETE => []
            ];

            foreach ($changListByProductId as $productId => $operation) {
                $changeList[$operation][] = $productId;
            }
            $this->changeList[$productType] = $changeList;
        }
        return $this->changeList[$productType];
    }

    /**
     * Get all product ids with their changes since the last export
     *
     * @param string $productType
     * @return array
     */
    public function getListByProductId(string $productType): array
    {
        if (!isset($this->changeListByProductId[$productType])) {
            $fromVersion = $this->getCurrentExportedVersion->execute();
            $toVersion = $this->changelogResource->getLatestVersionId();
            $this->changeListByProductId[$productType] = $this->getChangedProductIds(
                $fromVersion,
                $toVersion,
                $productType
            );
        }
        return $this->changeListByProductId[$productType];
    }

    /**
     * Get all added, updated or deleted products between the given versions
     *
     * @param int $fromVersionId
     * @param int $toVersionId
     * @param string $productType
     * @return array
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

        $changeList = [];
        foreach ($rows as $row) {
            $productId = (int)$row['product_id'];
            if (array_key_exists($productId, $changeList)) {
                // need to handle cases where a single product has multiple operations
                $updatedOperation = $this->combineOperations($changeList[$productId], $row['operation_type']);
                if ($updatedOperation !== null) {
                    $changeList[$productId] = $updatedOperation;
                } else {
                    unset($changeList[$productId]);
                }
            } else {
                $changeList[$productId] = $row['operation_type'];
            }
        }

        return $changeList;
    }

    /**
     * Reduces two consecutive operations into a single one
     *
     * For example, an add followed by an update is the same as an "add".
     * An "add" followed by a "delete" is the same as nothing happening.
     * An "add" followed by another "add" should not happen, but is treated as an "add"
     *
     * @param string $operationOne
     * @param string $operationTwo
     * @return string|null
     */
    private function combineOperations(string $operationOne, string $operationTwo): ?string
    {
        $operationResultMap = [
            Changelog::OPERATION_TYPE_ADD => [
                Changelog::OPERATION_TYPE_ADD => false, // should not happen
                Changelog::OPERATION_TYPE_UPDATE => Changelog::OPERATION_TYPE_ADD,
                Changelog::OPERATION_TYPE_DELETE => null
            ],
            Changelog::OPERATION_TYPE_UPDATE => [
                Changelog::OPERATION_TYPE_ADD => false, // should not happen
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
            return null;
        }
        $result = $operationResultMap[$operationOne][$operationTwo];
        if ($result === false) {
            // invalid sequence - treat first operation as correct
            $message = sprintf('Encountered invalid operation sequence: %s, %s', $operationOne, $operationTwo);
            $this->logger->error($message);
            return $operationOne;
        }
        return $result;
    }
}
