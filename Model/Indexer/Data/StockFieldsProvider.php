<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

/**
 * Adds basic stock information to product index data. Not recommended with frequest stock updates or when using
 * Magento Inventory. Instead, add stock information at the time of FE query.
 */
class StockFieldsProvider implements \Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\StockAttributeConfig
     */
    protected $stockAttributeConfig;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Aligent\FredhopperIndexer\Helper\StockAttributeConfig $stockAttributeConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->stockAttributeConfig = $stockAttributeConfig;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId)
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
