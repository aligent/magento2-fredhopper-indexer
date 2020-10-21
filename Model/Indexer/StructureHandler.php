<?php
namespace Aligent\FredhopperIndexer\Model\Indexer;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Search\Request\Dimension;

class StructureHandler implements \Magento\Framework\Indexer\IndexStructureInterface
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;
    /**
     * @var \Magento\Framework\Indexer\ScopeResolver\IndexScopeResolver
     */
    protected $indexScopeResolver;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\Indexer\ScopeResolver\IndexScopeResolver $indexScopeResolver
    ) {
        $this->resource = $resource;
        $this->indexScopeResolver = $indexScopeResolver;
    }

    /**
     * @inheritDoc
     */
    public function delete($index, array $dimensions = [])
    {
        $tableName = $this->indexScopeResolver->resolve($index, $dimensions);
        if ($this->resource->getConnection()->isTableExists($tableName)) {
            $this->resource->getConnection()->dropTable($tableName);
        }
    }

    /**
     * @inheritDoc
     */
    public function create($index, array $fields, array $dimensions = [])
    {
        $this->createWorkingIndexTable($this->indexScopeResolver->resolve($index, $dimensions));
    }

    /**
     * @param $tableName
     * @throws \Zend_Db_Exception
     */
    protected function createWorkingIndexTable($tableName)
    {
        $table = $this->resource->getConnection()->newTable($tableName)
            ->addColumn(
                'product_type',
                Table::TYPE_TEXT,
                1,
                [
                    'nullable' => false,
                    'default' => 'p'
                ],
                'Product Type (p=product,v=variant)'
            )->addColumn(
                'product_id',
                Table::TYPE_INTEGER,
                10,
                [
                    'unsigned' => true,
                    'nullable' => false
                ],
                'Product ID'
            )->addColumn(
                'parent_id',
                Table::TYPE_INTEGER,
                10,
                [
                    'unsigned' => true,
                    'nullable' => true
                ],
                'Parent Product ID'
            )->addColumn(
                'attribute_data',
                Table::TYPE_TEXT,
                '4g',
                [
                    'nullable' => false
                ],
                'JSON-encoded attribute data'
            )->addIndex(
                'idx_primary',
                ['product_type', 'product_id'],
                ['type' => AdapterInterface::INDEX_TYPE_PRIMARY]
            );

        $this->resource->getConnection()->createTable($table);
    }
}
