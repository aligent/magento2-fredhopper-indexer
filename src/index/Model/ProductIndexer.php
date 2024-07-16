<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model;

use Aligent\FredhopperIndexer\Model\Data\FredhopperDataProvider;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext as FulltextResource;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\RuntimeException;
use Magento\Framework\Indexer\ActionInterface as IndexerActionInterface;
use Magento\Framework\Indexer\DimensionalIndexerInterface;
use Magento\Framework\Indexer\DimensionProviderInterface;
use Magento\Framework\Mview\ActionInterface as MviewActionInterface;
use Magento\Store\Model\StoreDimensionProvider;

class ProductIndexer implements DimensionalIndexerInterface, IndexerActionInterface, MviewActionInterface
{
    private const INDEXER_ID = 'fredhopper';
    private const DEPLOYMENT_CONFIG_INDEXER_BATCHES = 'indexer/batch_size/';

    /**
     * @param DimensionProviderInterface $dimensionProvider
     * @param FredhopperDataProvider $fredhopperDataProvider
     * @param DataHandler $dataHandler
     * @param DeploymentConfig $deploymentConfig
     * @param FulltextResource $fulltextResource
     * @param int $batchSize
     */
    public function __construct(
        private readonly DimensionProviderInterface $dimensionProvider,
        private readonly FredhopperDataProvider $fredhopperDataProvider,
        private readonly DataHandler $dataHandler,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly FulltextResource $fulltextResource,
        private readonly int $batchSize = 1000
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function execute($ids): void
    {
        $this->executeList($ids);
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function executeFull(): void
    {
        $this->executeList([]);
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function executeList(array $ids): void
    {
        foreach ($this->dimensionProvider->getIterator() as $dimension) {
            try {
                $this->executeByDimensions($dimension, new \ArrayIterator($ids));
            } catch (FilesystemException|RuntimeException) {
                continue;
            }
        }
    }

    /**
     * @inheritDoc
     *
     * @throws LocalizedException
     */
    public function executeRow($id): void
    {
        if (!$id) {
            throw new LocalizedException(
                __('Cannot index data for an undefined product.')
            );
        }
        $this->executeList([$id]);
    }

    /**
     * @inheritDoc
     *
     * @throws FileSystemException
     * @throws RuntimeException
     * @throws LocalizedException
     */
    public function executeByDimensions(array $dimensions, \Traversable $entityIds): void
    {
        if (count($dimensions) > 1 || !isset($dimensions[StoreDimensionProvider::DIMENSION_NAME])) {
            throw new \InvalidArgumentException('Indexer "' . self::INDEXER_ID . '" supports only Store dimension');
        }
        $storeId = (int)$dimensions[StoreDimensionProvider::DIMENSION_NAME]->getValue();

        $entityIds = iterator_to_array($entityIds);
        if (empty($entityIds)) {
            $this->dataHandler->cleanIndex($dimensions);
            $this->dataHandler->saveIndex($dimensions, $this->fredhopperDataProvider->rebuildStoreIndex($storeId, []));
            return;
        }

        $batchSize = $this->deploymentConfig->get(
            self::DEPLOYMENT_CONFIG_INDEXER_BATCHES . self::INDEXER_ID . '/partial_reindex'
        ) ?? $this->batchSize;
        $batches = array_chunk($entityIds, $batchSize);
        foreach ($batches as $batch) {
            $this->processBatch($dimensions, $batch);
        }
    }

    /**
     * Process next batch of products
     *
     * @throws LocalizedException
     */
    private function processBatch(array $dimensions, array $entityIds): void
    {
        $storeId = (int)$dimensions[StoreDimensionProvider::DIMENSION_NAME]->getValue();
        $productIds = array_unique(
            array_merge($entityIds, $this->fulltextResource->getRelationsByChild($entityIds))
        );
        if ($this->dataHandler->isAvailable($dimensions)) {
            $this->dataHandler->deleteIndex($dimensions, new \ArrayIterator($productIds));
            $this->dataHandler->saveIndex(
                $dimensions,
                $this->fredhopperDataProvider->rebuildStoreIndex($storeId, $productIds)
            );
        }
    }
}
