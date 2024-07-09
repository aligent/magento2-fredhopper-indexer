<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\ResourceModel\Export;

use Magento\Framework\App\ResourceConnection;

class CurrentIds
{
    private const TABLE_NAME = 'fredhopper_current_ids';

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Set the current version and data IDs
     *
     * @param int $versionId
     * @param string $dataId
     * @return void
     */
    public function setCurrentIds(int $versionId, string $dataId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->update(
            self::TABLE_NAME,
            [
                'version_id' => $versionId,
                'data_id' => $dataId
            ]
        );
    }

    /**
     * Get the current version and data IDs
     *
     * @return array
     */
    public function getCurrentIds(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();
        $select->from(self::TABLE_NAME);
        return $connection->fetchRow($select);
    }
}
