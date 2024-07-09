<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export;

use Aligent\FredhopperIndexer\Api\Export\Data\ExportInterface;
use Aligent\FredhopperIndexer\Model\Export\Data\Files\CreateDirectory;
use Aligent\FredhopperIndexer\Model\Export\Data\Export;
use Aligent\FredhopperIndexer\Model\Export\Data\ExportFactory;
use Aligent\FredhopperIndexer\Model\Export\Data\Files\CreateZipFile;
use Aligent\FredhopperIndexer\Model\Export\Data\GenerateProductFiles;
use Aligent\FredhopperIndexer\Model\Export\Data\GenerateVariantFiles;
use Aligent\FredhopperIndexer\Model\Export\Data\IndexReplicaManagement;
use Aligent\FredhopperIndexer\Model\Export\Data\Products\GetCompleteChangeList;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Aligent\FredhopperIndexer\Model\ResourceModel\Export\Data\Export as ExportResource;
use Aligent\FredhopperIndexer\Model\ResourceModel\Index\Changelog;
use Psr\Log\LoggerInterface;

class GenerateIncrementalExport
{

    private const ZIP_FILE_NAME = 'data-incremental.zip';

    public function __construct(
        private readonly ExportFactory $exportFactory,
        private readonly ExportResource $exportResource,
        private readonly GetCompleteChangeList $getCompleteChangeList,
        private readonly IndexReplicaManagement $indexReplicaManagement,
        private readonly Changelog $changelogResource,
        private readonly CreateDirectory $createDirectory,
        private readonly GenerateProductFiles $generateProductFiles,
        private readonly GenerateVariantFiles $generateVariantFiles,
        private readonly CreateZipFile $createZipFile,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        try {
            // create export entity
            /** @var Export $export */
            $export = $this->exportFactory->create();
            $export->setExportType(ExportInterface::EXPORT_TYPE_INCREMENTAL);

            // get changelist(s) and apply
            $changedProducts = $this->getCompleteChangeList->execute(DataHandler::TYPE_PRODUCT);
            $changedVariants = $this->getCompleteChangeList->execute(DataHandler::TYPE_VARIANT);

            $productAddCount = count($changedProducts[Changelog::OPERATION_TYPE_ADD] ?? []);
            $productUpdateCount = count($changedProducts[Changelog::OPERATION_TYPE_UPDATE] ?? []);
            $productDeleteCount = count($changedProducts[Changelog::OPERATION_TYPE_DELETE] ?? []);
            $productCount = $productAddCount + $productUpdateCount + $productDeleteCount;

            $variantAddCount = count($changedVariants[Changelog::OPERATION_TYPE_ADD] ?? []);
            $variantUpdateCount = count($changedVariants[Changelog::OPERATION_TYPE_UPDATE] ?? []);
            $variantDeleteCount = count($changedVariants[Changelog::OPERATION_TYPE_DELETE] ?? []);
            $variantCount = $variantAddCount + $variantUpdateCount + $variantDeleteCount;

            // if there are no changes, just exit
            if ($productCount === 0 && $variantCount === 0) {
                $this->logger->info(__('No incremental changes. Skipping export'));
                return;
            }

            $export->setProductCount($productCount);
            $export->setVariantCount($variantCount);
            $export->setProductAddCount($productAddCount);
            $export->setProductUpdateCount($productUpdateCount);
            $export->setProductDeleteCount($productDeleteCount);
            $export->setVariantAddCount($variantAddCount);
            $export->setVariantUpdateCount($variantUpdateCount);
            $export->setVariantDeleteCount($variantDeleteCount);

            $lastVersionId = $this->changelogResource->getLatestVersionId();
            $export->setVersionId($lastVersionId);

            // create replica of index table to work from
            // avoid index being updated while export is running
            $this->indexReplicaManagement->createReplicaTable();

            // create directory
            $directory = $this->createDirectory->execute(true);
            $export->setDirectory($directory);

            // generate export files
            $productFiles = $this->generateProductFiles->execute(
                $directory,
                $this->extractIds($changedProducts),
                true
            );

            $variantFiles = $this->generateVariantFiles->execute(
                $directory,
                $this->extractIds($changedVariants),
                true
            );

            // replica table is no longer needed
            $this->indexReplicaManagement->dropReplicaTable();

            // create zip file
            $zipFilePath = $directory . DIRECTORY_SEPARATOR . self::ZIP_FILE_NAME;
            $this->createZipFile->execute($zipFilePath, array_merge($productFiles, $variantFiles));

            // save export
            $this->exportResource->save($export);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Get product ids from change set
     *
     * @param array $changedProducts
     * @return array
     */
    private function extractIds(array $changedProducts): array
    {
        $productIds = [];
        foreach ($changedProducts as $products) {
            foreach ($products as $productId) {
                $productIds[] = $productId;
            }
        }
        return $productIds;
    }
}
