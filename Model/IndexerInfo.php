<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model;

use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Aligent\FredhopperIndexer\Helper\SanityCheckConfig;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
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
    private ResourceConnection $resourceConnection;
    private SanityCheckConfig $sanityCheckConfig;
    private ProductCollectionFactory $productCollectionFactory;
    private CollectionFactory $indexerCollectionFactory;

    public function __construct(
        ResourceConnection $resourceConnection,
        SanityCheckConfig $sanityCheckConfig,
        ProductCollectionFactory $productCollectionFactory,
        CollectionFactory $indexerCollectionFactory
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->sanityCheckConfig = $sanityCheckConfig;
        $this->productCollectionFactory = $productCollectionFactory;
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

    /**
     * @param ViewInterface $view
     * @return int
     */
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

    /**
     * @return array
     */
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
        array_multisort(array_column($rows, 'title'), SORT_ASC, $rows);

        return $rows;
    }

    /**
     * @return array
     */
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

    public function getProductDeletes()
    {
        $maxProducts = $this->sanityCheckConfig->getReportProducts();
        if ($maxProducts <= 0) {
            return [];
        }

        $conn = $this->resourceConnection->getConnection();

        $baseTable = DataHandler::INDEX_TABLE_NAME;

        // Get store with greatest number of deleted products
        $select = $conn->select();
        $select->from($baseTable);
        $select->reset(Select::COLUMNS);
        $select->columns(['store_id', 'product_count' => 'COUNT(*)']);
        $select->where('product_type = ?', DataHandler::TYPE_PRODUCT);
        $select->where('operation_type = ?', DataHandler::OPERATION_TYPE_DELETE);
        $select->group(['store_id']);
        $select->order(['product_count DESC']);
        $select->limit(1);

        try {
            $storeId = $conn->fetchOne($select);
        } catch (\Throwable $ex) {
            return [];
        }
        if (empty($storeId)) {
            return [];
        }

        // Get list of deleted products associated with that store
        $select = $conn->select();
        $select->from($baseTable);
        $select->reset(Select::COLUMNS);
        $select->columns(['product_id']);
        $select->where('product_type = ?', DataHandler::TYPE_PRODUCT);
        $select->where('operation_type = ?', DataHandler::OPERATION_TYPE_DELETE);
        $select->where('store_id = ?', $storeId);

        $productIds = [];
        try {
            $productIds = $conn->fetchCol($select);
        } catch (\Throwable $ex) {
            return [];
        }
        if (empty($productIds)) {
            return [];
        }

        // get SKU, name of deleted products for inclusion in email
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $productCollection */
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addAttributeToSelect(['sku', 'name']);
        $productCollection->addIdFilter($productIds);
        $productCollection->setStore(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
        $select = $productCollection->getSelect();
        $select->order('RAND()');
        $select->limit($maxProducts);

        $allProducts = [];
        foreach ($productCollection as $product) {
            $productId = $product->getId();
            $allProducts[$productId] = [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
            ];
        }

        return $allProducts;
    }
}
