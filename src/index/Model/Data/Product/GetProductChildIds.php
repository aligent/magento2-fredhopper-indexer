<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Data\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\EntityManager\MetadataPool;

class GetProductChildIds
{

    /**
     * @param ResourceConnection $resourceConnection
     * @param MetadataPool $metadataPool
     * @param GetProductTypeInstance $getProductTypeInstance
     * @param GetProductEmulator $getProductEmulator
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MetadataPool $metadataPool,
        private readonly GetProductTypeInstance $getProductTypeInstance,
        private readonly GetProductEmulator $getProductEmulator
    ) {
    }

    /**
     * Get all child IDs for the given product
     *
     * @param int $productId
     * @param string $typeId
     * @return array|null
     * @throws \Exception
     */
    public function execute(int $productId, string $typeId): ?array
    {
        $connection = $this->resourceConnection->getConnection();
        $typeInstance = $this->getProductTypeInstance->execute($typeId);
        /** @var Product $productEmulator */
        $productEmulator = $this->getProductEmulator->execute($typeId);
        $relation = $typeInstance->isComposite($productEmulator)
            ? $typeInstance->getRelationInfo()
            : false;

        if (!$relation) {
            return null;
        }
        $tableName = $relation->getData('table');
        $parentFieldName = $relation->getData('parent_field_name');
        $childFieldName = $relation->getData('child_field_name');
        if (!$tableName || !$parentFieldName || !$childFieldName) {
            return null;
        }

        $select = $connection->select()->from(
            ['main' => $this->resourceConnection->getTableName($tableName)],
            [$childFieldName]
        );
        $select->join(
            ['e' => $this->resourceConnection->getTableName('catalog_product_entity')],
            'e.' . $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField() .
            ' = main.' . $parentFieldName
        )->where(
            'e.entity_id = ?',
            $productId
        );

        if ($relation->getData('where') !== null) {
            $select->where($relation->getData('where'));
        }

        return $connection->fetchCol($select);
    }
}
