<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Products;

use Aligent\FredhopperIndexer\Model\DataHandler;
use Magento\Framework\App\ResourceConnection;

readonly class GetAllProductIds
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get all product or variant IDs to be exported
     *
     * @param string $productType
     * @return array
     */
    public function execute(string $productType): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();
        $select->from(DataHandler::INDEX_TABLE_NAME, ['product_id']);
        $select->where('product_type = ?', $productType);
        $select->distinct();
        $select->order('product_id');
        $rows = $connection->fetchAll($select);
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row['product_id'];
        }
        return $ids;
    }
}
