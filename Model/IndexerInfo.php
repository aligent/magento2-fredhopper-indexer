<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model;

use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\StateInterface;
use Magento\Framework\Mview\View;
use Magento\Framework\Mview\ViewInterface;
use Magento\Indexer\Model\Indexer\CollectionFactory;
use Magento\Indexer\Model\Indexer\DependencyDecorator;
use Zend_Db_Select;

/**
 * Gets information about Magento indexers, and temp tables used by the Fredhopper indexer
 */
class IndexerInfo
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var CollectionFactory
     */
    protected $indexerCollectionFactory;

    public function __construct(
        ResourceConnection $resourceConnection,
        CollectionFactory $indexerCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->indexerCollectionFactory = $indexerCollectionFactory;
    }

    /**
     * @param IndexerInterface $indexer
     * @return string
     * @see \Magento\Indexer\Console\Command\IndexerStatusCommand::getStatus
     */
    public function getIndexerStatus(IndexerInterface $indexer): string
    {
        $status = 'unknown';
        switch ($indexer->getStatus()) {
            case StateInterface::STATUS_VALID:
                $status = 'Ready';
                break;
            case StateInterface::STATUS_INVALID:
                $status = 'Reindex required';
                break;
            case StateInterface::STATUS_WORKING:
                $status = 'Processing';
                break;
        }
        return $status;
    }

    public function getPendingCount(ViewInterface $view): int
    {
        $changelog = $view->getChangelog();
        try {
            $currentVersionId = $changelog->getVersion();
        } catch (\Exception $e) {
            return 0;
        }

        $state = $view->getState();
        return count($changelog->getList($state->getVersionId(), $currentVersionId));
    }

    public function getIndexState(): array
    {
        $rows = [];
        $indexers = $this->indexerCollectionFactory->create()->getItems();

        /** @var DependencyDecorator $indexer */
        foreach ($indexers as $indexer) {
            /** @var View $view */
            $view = $indexer->getView();
            $pending = $this->getPendingCount($view);
            $rows[] = [
                'id' => $indexer->getIndexerId(),
                'title' => $indexer->getTitle(),
                'status' => $this->getIndexerStatus($indexer),
                'schedule_status' => $view->getState()->getStatus(),
                'schedule_backlog' => $pending,
                'schedule_updated' => $view->getState()->getUpdated(),
            ];
        }

        // Ensure same order as bin/magento indexer:status
        usort(
            $rows,
            function (array $comp1, array $comp2) {
                return strcmp($comp1['title'], $comp2['title']);
            }
        );

        return $rows;
    }

    public function getFredhopperIndexState(): array
    {
        $conn = $this->resourceConnection->getConnection();

        $ops = [
            DataHandler::OPERATION_TYPE_ADD => 'add',
            DataHandler::OPERATION_TYPE_DELETE => 'delete',
            DataHandler::OPERATION_TYPE_UPDATE => 'update',
            DataHandler::OPERATION_TYPE_REPLACE => 'replace',
        ];
        $baseTable = DataHandler::INDEX_TABLE_NAME;
        $result = [];

        $select = $conn->select();
        $select->from($baseTable);
        $select->reset(Zend_Db_Select::COLUMNS);
        $select->columns(['store' => 'store_id', 'type' => 'product_type']);
        foreach ($ops as $op => $label) {
            // N.B. both values are defined in code; safe SQL-injection
            $select->columns([$label => "SUM(operation_type = '$op')"]);
        }
        $select->group(['store_id', 'product_type']);
        try {
            $rows = $conn->fetchAll($select);
        } catch (\Throwable $ex) {
            return [['Error' => 'Error']];
        }

        $storeIds = [];
        foreach ($rows as $row) {
            $key = $row['store'] . '-' . $row['type'];
            $storeId = (int)$row['store'];
            if ($storeId > 0 && !isset($storeIds[$storeId])) {
                $storeIds[$storeId] = true;
            }
            $resultRow = $row;
            $resultRow['scope'] = 'N/A';
            $result[$key] = $resultRow;
        }

        foreach ($storeIds as $storeId => $ignore) {
            $select = $conn->select();
            $select->from($baseTable . '_scope' . $storeId);
            $select->reset(Zend_Db_Select::COLUMNS);
            $select->columns(['product_type', 'total_count' => 'COUNT(*)']);
            $select->group('product_type');
            try {
                $rows = $conn->fetchAll($select);
            } catch (\Throwable $ex) {
                continue;
            }
            foreach ($rows as $row) {
                $key = $storeId . '-' . $row['product_type'];
                $result[$key]['scope'] = $row['total_count'];
            }
        }

        // No longer need store_id - product_type key
        return array_values($result);
    }
}
