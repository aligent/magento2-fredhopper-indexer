<?php
namespace Aligent\FredhopperIndexer\Model\Indexer;

use Magento\Framework\Search\Request\Dimension;

class DataHandler implements \Magento\Framework\Indexer\SaveHandler\IndexerInterface
{
    const BATCH_SIZE = 1000;
    const INDEX_TABLE_NAME = 'fredhopper_product_data_index';
    const TYPE_PRODUCT = 'p';
    const TYPE_VARIANT = 'v';
    const OPERATION_TYPE_ADD = 'a';
    const OPERATION_TYPE_DELETE = 'd';
    const OPERATION_TYPE_UPDATE = 'u';
    const OPERATION_TYPE_REPLACE = 'r';

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;
    /**
     * @var \Magento\Framework\Indexer\ScopeResolver\IndexScopeResolver
     */
    protected $indexScopeResolver;
    /**
     * @var \Magento\Framework\Indexer\SaveHandler\Batch
     */
    protected $batch;
    /**
     * @var \Magento\Framework\App\ScopeResolverInterface
     */
    protected $scopeResolver;
    /**
     * @var \Magento\Framework\Indexer\IndexStructureInterface
     */
    protected $indexStructure;
    /**
     * @var \Aligent\FredhopperIndexer\Model\Indexer\Data\FredhopperDataProvider
     */
    protected $dataProvider;
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\GeneralConfig
     */
    protected $generalConfig;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\AttributeConfig
     */
    protected $attributeConfig;
    /**
     * @var \Aligent\FredhopperIndexer\Api\Indexer\Data\DocumentProcessorInterface[]
     */
    protected $documentPreProcessors;
    /**
     * @var \Aligent\FredhopperIndexer\Api\Indexer\Data\DocumentProcessorInterface[]
     */
    protected $documentPostProcessors;
    /**
     * @var int
     */
    protected $batchSize;
    /**
     * @var string[]
     */
    protected $variantPriceAttributes = [
        'regular_price',
        'special_price'
    ];

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\Indexer\ScopeResolver\IndexScopeResolver $indexScopeResolver,
        \Magento\Framework\Indexer\SaveHandler\Batch $batch,
        \Magento\Framework\App\ScopeResolverInterface $scopeResolver,
        \Magento\Framework\Indexer\IndexStructureInterface $indexStructure,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Aligent\FredhopperIndexer\Model\Indexer\Data\FredhopperDataProvider $dataProvider,
        \Aligent\FredhopperIndexer\Helper\GeneralConfig $generalConfig,
        \Aligent\FredhopperIndexer\Helper\AttributeConfig $attributeConfig,
        array $documentPreProcessors = [],
        array $documentPostProcessors = [],
        $batchSize = 1000
    ) {
        $this->resource = $resource;
        $this->indexScopeResolver = $indexScopeResolver;
        $this->batch = $batch;
        $this->scopeResolver = $scopeResolver;
        $this->indexStructure = $indexStructure;
        $this->json = $json;
        $this->dataProvider = $dataProvider;
        $this->generalConfig = $generalConfig;
        $this->attributeConfig = $attributeConfig;
        $this->documentPreProcessors = $documentPreProcessors;
        $this->documentPostProcessors = $documentPostProcessors;
        $this->batchSize = $batchSize;
    }

    /**
     * @inheritDoc
     */
    public function saveIndex($dimensions, \Traversable $documents)
    {
        $scopeId = $this->getScopeId($dimensions);
        $products = [];
        $variants = [];
        foreach ($this->batch->getItems($documents, self::BATCH_SIZE) as $documents) {
            $this->processDocuments($documents, $scopeId);
            foreach($documents as $productId => $productData) {
                $products[$productId] = $productData['product'];
                foreach ($productData['variants'] as $variantId => $variantData) {
                    $variants[$variantId] = $variantData;
                }
            }
        }

        $variantIdParentMapping = $this->dataProvider->getVariantIdParentMapping();
        // have total makeup of products and variants now
        // first, insert into the working table
        $this->insertProductData($dimensions, $products, $variants, $variantIdParentMapping);
        // compare with current values and update records where needed
        $this->applyProductChanges($dimensions);
    }

    /**
     * Final processing of attributes to ensure attributes are sent at correct level (product/variant), and that
     * site variants are appended where needed.
     * Also provide pre/post processors for custom code to hook into
     * @param array $documents
     * @param $scopeId
     */
    protected function processDocuments(array &$documents, $scopeId) : void
    {
        foreach ($this->documentPreProcessors as $preProcessor) {
            $preProcessor->processDocuments($documents, $scopeId);
        }
        if (!empty($documents)) {
            // if using variants, need to ensure each product has a variant
            if ($this->attributeConfig->getUseVariantProducts()) {
                foreach ($documents as $productId => &$data) {
                    // copy product data to variant
                    if (empty($data['variants'])) {
                        $data['variants'] =[
                            $productId => $data['product']
                        ];
                    }

                    // remove any variant-level attributes from parent product, ensuring it is set on each variant
                    foreach ($data['product'] as $attributeCode => $productData) {
                        if (in_array($attributeCode, $this->attributeConfig->getVariantAttributeCodes())) {
                            foreach ($data['variants'] as $variantId => &$variantData) {
                                $variantData[$attributeCode] = $variantData[$attributeCode] ?? $productData;
                            }
                            unset($data['product'][$attributeCode]);
                            continue; // continue with the next attribute
                        }
                        // check pricing attributes
                        // need to use strpos as we can have customer group pricing
                        foreach ($this->variantPriceAttributes as $priceAttributePrefix) {
                            if (strpos($attributeCode, $priceAttributePrefix) === 0) {
                                foreach ($data['variants'] as $variantId => &$variantData) {
                                    $variantData[$attributeCode] = $variantData[$attributeCode] ?? $productData;
                                }
                                unset($data['product'][$attributeCode]);
                                break; // skip the rest of the pricing attribute loop
                            }
                        }
                    }
                }
            } else {
                // need to collate variant level attributes at product level
                // keep them at variant level also - variant data won't be sent, but can be used to trigger resending
                // of parent data
                foreach ($documents as $productId => &$data) {
                    foreach ($this->attributeConfig->getVariantAttributeCodes() as $variantAttributeCode) {
                        // convert product attribute to an array if it's not already
                        if (isset($data['product'][$variantAttributeCode]) &&
                            !is_array($data['product'][$variantAttributeCode])) {
                            $data['product'][$variantAttributeCode] = [$data['product'][$variantAttributeCode]];
                        }
                        $valueArray = [];
                        foreach ($data['variants'] as $variantId => $variantData) {
                            if (isset($variantData[$variantAttributeCode])) {
                                $value = $variantData[$variantAttributeCode];
                                $value = is_array($value) ? $value : [$value];
                                $valueArray = array_merge($valueArray, $value);
                            }
                        }
                        // if there are variant values to include, ensure product value is set
                        if (!empty($valueArray)) {
                            $data['product'][$variantAttributeCode] = $data['product'][$variantAttributeCode] ?? [];
                            $data['product'][$variantAttributeCode] = array_merge(
                                $data['product'][$variantAttributeCode],
                                $valueArray
                            );
                        }
                    }
                }
            }
        }
        foreach ($this->documentPostProcessors as $postProcessor) {
            $postProcessor->processDocuments($documents, $scopeId);
        }
    }

    protected function insertProductData(
        $dimensions,
        array $products,
        array $variants,
        array $variantIdParentMapping
    ) : void {
        $productRows = [];
        foreach ($products as $productId => $attributeData) {
            $productRows[] = [
                'product_type' => self::TYPE_PRODUCT,
                'product_id' => $productId,
                'attribute_data' => $this->json->serialize($this->sortArray($attributeData))
            ];
        }

        $variantRows = [];
        foreach ($variants as $variantId => $attributeData) {
            $variantRows[] = [
                'product_type' => self::TYPE_VARIANT,
                'product_id' => $variantId,
                'parent_id' => $variantIdParentMapping[$variantId] ?? $variantId, // dummy variants have themselves as parents
                'attribute_data' => $this->json->serialize($this->sortArray($attributeData))
            ];
        }
        foreach (array_chunk($productRows, $this->batchSize) as $batchRows) {
            $this->resource->getConnection()
                ->insertMultiple($this->getTableName($dimensions), $batchRows);
        }

        foreach (array_chunk($variantRows, $this->batchSize) as $batchRows) {
            $this->resource->getConnection()
                ->insertMultiple($this->getTableName($dimensions), $batchRows);
        }
    }

    /**
     * Insert/update records in main index table from store-level table
     * Ensures correct deltas are sent to Fredhopper
     * @param $dimensions
     */
    protected function applyProductChanges($dimensions) : void
    {
        $connection = $this->resource->getConnection();
        $indexTableName = self::INDEX_TABLE_NAME;
        $scopeTableName = $this->getTableName($dimensions);
        $storeId = $this->getScopeId($dimensions);

        // insert any new records and mark as "add"
        /*
         * INSERT IGNORE INTO fredhopper_product_data_index
         * SELECT :scopeId as store_id, product_type, product_id, attribute_data, 'a' as operation_type
         *   FROM fredhopper_product_data_index_store<id>;
         *
         */
        $insertQuery = "INSERT IGNORE INTO {$indexTableName}" .
            " (store_id, product_type, product_id, parent_id, attribute_data, operation_type)" .
            " SELECT :store_id, product_type, product_id, parent_id, attribute_data, :operation_type" .
            " FROM {$scopeTableName}";
        $connection->query($insertQuery, [
            'store_id' => $storeId,
            'operation_type' => self::OPERATION_TYPE_ADD
        ]);


        // check for deleted records and mark as "delete"
        /**
         * UPDATE fredhopper_product_data_index main
         *    SET operation_type = 'd'
         *  WHERE store_id = :scopeId
         *    AND (product_id IN (:affectedProductIds) or :affectedProductIds = -1)
         *    AND NOT EXISTS (SELECT 1
         *                      FROM fredhopper_product_data_index_store<id> store_index
         *                     WHERE store_index.product_id = main.product_id
         *                       AND store_index.product_type = main.product_type)
         */
        $deleteQuery = "UPDATE {$indexTableName} main_table" .
            " SET operation_type = :operation_type " .
            " WHERE store_id = :store_id AND NOT EXISTS (SELECT 1 from {$scopeTableName} scope_table " .
            " WHERE scope_table.product_id = main_table.product_id " .
            " AND scope_table.product_type = main_table.product_type)";
        $connection->query($deleteQuery, [
            'store_id' => $storeId,
            'operation_type' => self::OPERATION_TYPE_DELETE
        ]);

        // compare
        /**
         * UPDATE fredhopper_product_data_index main,
         *        fredhopper_product_data_index_store<id> store_index
         *    SET main.operation_type = 'r',
         *        main.attribute_data = store_index.attribute_data
         *  WHERE main.store_id = :scopeId
         *    AND main.product_type = store_index.product_type
         *    AND main.product_id = store_index.product_id
         *    AND main.attribute_data != store_index.attribute_data
         */
        $updateQuery = "UPDATE {$indexTableName} main_table, {$scopeTableName} scope_table" .
            " SET main_table.operation_type = :operation_type, main_table.attribute_data = scope_table.attribute_data" .
            " WHERE main_table.store_id = :store_id" .
            " AND main_table.product_id = scope_table.product_id" .
            " AND main_table.product_type = scope_table.product_type" .
            " AND main_table.attribute_data <> scope_table.attribute_data";
        $connection->query($updateQuery, [
            'store_id' => $storeId,
            'operation_type' => self::OPERATION_TYPE_REPLACE
        ]);
    }

    /**
     * Function used to "reset" main index table after performing an incremental update
     */
    public function resetIndexAfterExport()
    {
        $connection = $this->resource->getConnection();
        $indexTableName = self::INDEX_TABLE_NAME;
        // first, remove any records marked for deletion
        $deleteQuery = "DELETE FROM {$indexTableName} WHERE operation_type = :operation_type";
        $connection->query($deleteQuery, ['operation_type' => self::OPERATION_TYPE_DELETE]);

        // where clause is not technically needed, but operation_type column is indexed, so this should reduce the
        // amount of work and maintain/improve performance
        $updateQuery = "UPDATE {$indexTableName} SET operation_type = NULL WHERE operation_type IS NOT NULL";
        $connection->query($updateQuery);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteIndex($dimensions, \Traversable $documents)
    {
        foreach ($this->batch->getItems($documents, $this->batchSize) as $batchDocuments) {
            $this->resource->getConnection()
                ->delete($this->getTableName($dimensions), ['product_id in (?)' => $batchDocuments]);
        }
    }

    /**
     * @inheritDoc
     */
    public function cleanIndex($dimensions)
    {
        $this->indexStructure->delete(self::INDEX_TABLE_NAME, $dimensions);
        $this->indexStructure->create(self::INDEX_TABLE_NAME, [], $dimensions);
    }

    /**
     * @inheritDoc
     */
    public function isAvailable($dimensions = [])
    {
        return $this->resource->getConnection()->isTableExists($this->getTableName($dimensions));
    }

    /**
     * @param Dimension[] $dimensions
     * @return int
     */
    protected function getScopeId(array $dimensions) : int
    {
        $dimension = current($dimensions);
        return $this->scopeResolver->getScope($dimension->getValue())->getId();
    }

    /**
     * @param Dimension[] $dimensions
     * @return string
     */
    protected function getTableName(array $dimensions) : string
    {
        return $this->indexScopeResolver->resolve(self::INDEX_TABLE_NAME, $dimensions);
    }

    /**
     * Function to recursively sort an array by key (or value if keys are numeric) for ease of comparison by string
     * @param array $array
     * @return array
     */
    protected function sortArray(array $array) : array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortArray($value);
            }
        }
        reset($array);
        if (is_numeric(key($array))) {
            asort($array);
            $array = array_values($array); // reorder numeric keys
        } else {
            ksort($array);
        }
        return $array;
    }
}
