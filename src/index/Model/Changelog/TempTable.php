<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Changelog;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Random\RandomException;

class TempTable
{
    private const string TEMP_TABLE_PREFIX = DataHandler::INDEX_TABLE_NAME . '_temp_';

    private string $tempTableName;

    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Gets the current temporary table name
     *
     * @return string
     * @throws LocalizedException
     */
    public function getTempTableName(): string
    {
        if (!isset($this->tempTableName)) {
            throw new LocalizedException(__(__METHOD__ . ': temp table name not set'));
        }
        return $this->tempTableName;
    }

    /**
     * Sets the current temporary table to a unique value
     *
     * @return void
     * @throws LocalizedException
     */
    public function generateTempTableName(): void
    {
        if (isset($this->tempTableName)) {
            throw new LocalizedException(__(__METHOD__ . ': temp table name already set'));
        }
        try {
            $tempTableName = self::TEMP_TABLE_PREFIX . bin2hex(random_bytes(4));
        } catch (RandomException) {
            $tempTableName = uniqid(self::TEMP_TABLE_PREFIX, true);
        }
        $this->tempTableName = $tempTableName;
    }

    /**
     * Creates a temporary copy of the index table for use in generating changelog records
     *
     * @return void
     * @throws LocalizedException
     */
    public function create(): void
    {
        if (!isset($this->tempTableName)) {
            throw new LocalizedException(__(__METHOD__ . ': temp table name not set'));
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $connection->createTableByDdl(DataHandler::INDEX_TABLE_NAME, $this->tempTableName);
        if ($connection->isTableExists($this->tempTableName)) {
            throw new LocalizedException(__(__METHOD__ . ': temp table already exists'));
        }
        try {
            $connection->createTable($table);
        } catch (\Exception $e) {
            throw new LocalizedException(__(__METHOD__ . ': ' . $e->getMessage()), $e);
        }
        $copySelect = $connection->select();
        $copySelect->from(DataHandler::INDEX_TABLE_NAME);
        $copyInsert = $connection->insertFromSelect(
            $copySelect,
            $this->tempTableName
        );
        $connection->query($copyInsert);
    }

    /**
     * Drops the temporary table if it exists
     *
     * @return void
     * @throws LocalizedException
     */
    public function drop(): void
    {
        if (!isset($this->tempTableName)) {
            throw new LocalizedException(__(__METHOD__ . ': temp table name not set'));
        }
        $this->resourceConnection->getConnection()->dropTable($this->tempTableName);
        unset($this->tempTableName);
    }
}
