<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model;

use Aligent\FredhopperCommon\Model\Config\AttributeConfig;
use Aligent\FredhopperIndexer\Api\Data\DocumentProcessorInterface;
use Aligent\FredhopperIndexer\Model\Data\ApplyProductChanges;
use Aligent\FredhopperIndexer\Model\Data\InsertProductData;
use Aligent\FredhopperIndexer\Model\Data\Process\ProcessProducts;
use Aligent\FredhopperIndexer\Model\Data\Process\ProcessVariants;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ScopeResolverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Indexer\IndexStructureInterface;
use Magento\Framework\Indexer\SaveHandler\Batch;
use Magento\Framework\Indexer\SaveHandler\IndexerInterface;
use Magento\Framework\Indexer\ScopeResolver\IndexScopeResolver;
use Magento\Framework\Search\Request\Dimension;

class DataHandler implements IndexerInterface
{
    public const string INDEX_TABLE_NAME = 'fredhopper_product_data_index';

    public const string TYPE_PRODUCT = 'p';
    public const string TYPE_VARIANT = 'v';

    private const int BATCH_SIZE = 1000;

    /**
     * @param ResourceConnection $resource
     * @param IndexScopeResolver $indexScopeResolver
     * @param ScopeResolverInterface $scopeResolver
     * @param IndexStructureInterface $indexStructure
     * @param Batch $batch
     * @param AttributeConfig $attributeConfig
     * @param ProcessProducts $processProducts
     * @param ProcessVariants $processVariants
     * @param InsertProductData $insertProductData
     * @param ApplyProductChanges $applyProductChanges
     * @param array $documentPreProcessors
     * @param array $documentPostProcessors
     * @param int $batchSize
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly IndexScopeResolver $indexScopeResolver,
        private readonly ScopeResolverInterface $scopeResolver,
        private readonly IndexStructureInterface $indexStructure,
        private readonly Batch $batch,
        private readonly AttributeConfig $attributeConfig,
        private readonly ProcessProducts $processProducts,
        private readonly ProcessVariants $processVariants,
        private readonly InsertProductData $insertProductData,
        private readonly ApplyProductChanges $applyProductChanges,
        private readonly array $documentPreProcessors = [],
        private readonly array $documentPostProcessors = [],
        private readonly int $batchSize = 1000
    ) {
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function saveIndex($dimensions, \Traversable $documents): void
    {
        $scopeId = $this->getScopeId($dimensions);
        $products = [];
        $variants = [];
        foreach ($this->batch->getItems($documents, self::BATCH_SIZE) as $documents) {
            $documents = $this->processDocuments($documents, $scopeId);
            foreach ($documents as $productId => $productData) {
                $products[$productId] = $productData['product'];
                foreach ($productData['variants'] as $variantId => $variantData) {
                    $variants[$variantId] = $variantData;
                }
            }
        }

        // have total makeup of products and variants now
        // first, insert into the working table
        $scopeTableName = $this->getTableName($dimensions);
        $this->insertProductData->execute($scopeTableName, $products, $variants, $this->batchSize);
        // compare with current values and update records where needed
        $this->applyProductChanges->execute($scopeTableName, $scopeId);
    }

    /**
     * Final processing of attributes to ensure attributes are sent at correct level (product/variant)
     *
     * Also ensure site variants are appended where needed, and  provide pre- / post-processors for custom code
     *
     * @param array $documents
     * @param int $scopeId
     * @return array
     * @throws LocalizedException
     */
    public function processDocuments(array $documents, int $scopeId) : array
    {
        foreach ($this->documentPreProcessors as $preProcessor) {
            /** @var DocumentProcessorInterface $preProcessor */
            $documents = $preProcessor->processDocuments($documents, $scopeId);
        }
        if (!empty($documents)) {
            // if using variants, need to ensure each product has a variant
            if ($this->attributeConfig->getUseVariantProducts()) {
                $documents = $this->processVariants->execute($documents);
            } else {
                $documents = $this->processProducts->execute($documents);
            }
        }
        foreach ($this->documentPostProcessors as $postProcessor) {
            /** @var DocumentProcessorInterface $postProcessor */
            $documents = $postProcessor->processDocuments($documents, $scopeId);
        }
        return $documents;
    }

    /**
     * @inheritDoc
     */
    public function deleteIndex($dimensions, \Traversable $documents): void
    {
        foreach ($this->batch->getItems($documents, $this->batchSize) as $batchDocuments) {
            $this->resource->getConnection()
                ->delete($this->getTableName($dimensions), ['product_id in (?)' => $batchDocuments]);
            $this->resource->getConnection()
                ->delete($this->getTableName($dimensions), ['parent_id in (?)' => $batchDocuments]);
        }
    }

    /**
     * @inheritDoc
     */
    public function cleanIndex($dimensions): void
    {
        $this->indexStructure->delete(self::INDEX_TABLE_NAME, $dimensions);
        $this->indexStructure->create(self::INDEX_TABLE_NAME, [], $dimensions);
    }

    /**
     * @inheritDoc
     */
    public function isAvailable($dimensions = []): bool
    {
        return $this->resource->getConnection()->isTableExists($this->getTableName($dimensions));
    }

    /**
     * Extract the current ID from the dimension array
     *
     * @param Dimension[] $dimensions
     * @return int
     */
    private function getScopeId(array $dimensions) : int
    {
        $dimension = current($dimensions);
        return (int)$this->scopeResolver->getScope($dimension->getValue())->getId();
    }

    /**
     * Get the scoped table name from the dimension array
     *
     * @param Dimension[] $dimensions
     * @return string
     */
    private function getTableName(array $dimensions) : string
    {
        return $this->indexScopeResolver->resolve(self::INDEX_TABLE_NAME, $dimensions);
    }
}
