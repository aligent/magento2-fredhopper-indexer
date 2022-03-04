<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Helper\StockAttributeConfig;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Adds basic stock information to product index data. Not recommended with frequest stock updates or when using
 * Magento Inventory. Instead, add stock information at the time of FE query.
 */
class StockFieldsProvider implements AdditionalFieldsProviderInterface
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var StockAttributeConfig
     */
    protected $stockAttributeConfig;

    public function __construct(
        ResourceConnection $resourceConnection,
        StockAttributeConfig $stockAttributeConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->stockAttributeConfig = $stockAttributeConfig;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId): array
    {
        $result = [];
        // only add stock information if enabled
        if ($this->stockAttributeConfig->getSendStockCount() || $this->stockAttributeConfig->getSendStockStatus()) {
            $connection = $this->resourceConnection->getConnection();
            $select = $connection->select()->from(
                'cataloginventory_stock_status',
                ['product_id', 'qty', 'stock_status']
            );
            if ($productIds) {
                $select->where('product_id IN (?)', $productIds);
            }

            foreach ($connection->fetchAll($select) as $row) {
                $stockInfo = [];
                if ($this->stockAttributeConfig->getSendStockCount()) {
                    $stockInfo['stock_qty'] = $row['qty'];
                }
                if ($this->stockAttributeConfig->getSendStockStatus()) {
                    $stockInfo['stock_status'] = $row['stock_status'];
                }
                $result[$row['product_id']] = $stockInfo;
            }
        }
        return $result;
    }
}
