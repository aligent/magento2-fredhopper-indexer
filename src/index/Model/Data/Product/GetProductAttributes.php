<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Data\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\EntityManager\MetadataPool;
use Zend_Db_Expr;

class GetProductAttributes
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly MetadataPool $metadataPool
    ) {
    }

    /**
     * Get specified attribute data for given products
     *
     * @param int $storeId
     * @param array $productIds
     * @param array $attributesByBackendType
     * @param array $staticAttributes
     * @return array
     * @throws \Zend_Db_Select_Exception
     * @throws \Zend_Db_Statement_Exception
     * @throws \Exception
     */
    public function execute(
        int $storeId,
        array $productIds,
        array $attributesByBackendType,
        array $staticAttributes
    ): array {
        $result = [];
        $selects = [];

        $connection = $this->resourceConnection->getConnection();
        $ifStoreValue = $connection->getCheckSql('t_store.value_id > 0', 't_store.value', 't_default.value');
        $linkField = $this->metadataPool->getMetadata(ProductInterface::class)->getLinkField();

        $productLinkFieldsToEntityIdMap = $connection->fetchPairs(
            $connection->select()->from(
                ['cpe' => $this->resourceConnection->getTableName('catalog_product_entity')],
                [$linkField, 'entity_id']
            )->where(
                'cpe.entity_id IN (?)',
                $productIds
            )
        );

        foreach ($attributesByBackendType as $backendType => $attributeIds) {
            if (empty($attributeIds)) {
                continue;
            }
            $tableName = $this->resourceConnection->getTableName('catalog_product_entity_' . $backendType);

            $select = $connection->select();
            $select->from(
                ['t' => $tableName],
                [
                    $linkField => 't.' . $linkField,
                    'attribute_id' => 't.attribute_id',
                    'value' => $this->unifyField($ifStoreValue, $backendType)
                ]
            );
            $select->joinLeft(
                ['t_store' => $tableName],
                $connection->quoteInto(
                    't.' . $linkField . ' = t_store.' . $linkField .
                    ' AND t.attribute_id = t_store.attribute_id AND t_store.store_id = ?',
                    $storeId
                ),
                []
            );
            $select->joinLeft(
                ['t_default' => $tableName],
                $connection->quoteInto(
                    't.' . $linkField . ' = t_default.' . $linkField .
                    ' AND t.attribute_id = t_default.attribute_id AND t_default.store_id = ?',
                    0
                ),
                []
            );
            $select->where(' t.attribute_id IN (?)', $attributeIds);
            $select->where('t.' . $linkField . ' IN (?)', array_keys($productLinkFieldsToEntityIdMap));
            $selects[] = $select;
        }

        if ($selects) {
            $select = $connection->select()->union($selects, Select::SQL_UNION_ALL);
            $query = $connection->query($select);
            while ($row = $query->fetch()) {
                $entityId = $productLinkFieldsToEntityIdMap[$row[$linkField]];
                $result[$entityId][$row['attribute_id']] = $row['value'];
            }
        }

        // add static attributes from main table
        if (!empty($staticAttributes)) {
            $attributeIds = array_flip($staticAttributes);
            $select = $connection->select();
            $select->from(['e' => 'catalog_product_entity'], array_merge(['entity_id', 'type_id'], $staticAttributes));
            $select->where('e.entity_id IN (?)', $productLinkFieldsToEntityIdMap);

            foreach ($connection->query($select) as $row) {
                $entityId = $row['entity_id'];
                unset($row['entity_id']);
                foreach ($row as $column => $value) {
                    if (array_key_exists($column, $attributeIds)) {
                        $result[$entityId][$attributeIds[$column]] = $value;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Format any date fields appropriately
     *
     * @param Zend_Db_Expr $field
     * @param string $backendType
     * @return Zend_Db_Expr
     */
    private function unifyField(Zend_Db_Expr $field, string $backendType): Zend_Db_Expr
    {
        if ($backendType !== 'datetime') {
            return $field;
        }
        return $this->resourceConnection->getConnection()->getDateFormatSql($field, '%Y-%m-%d %H:%i:%s');
    }
}
