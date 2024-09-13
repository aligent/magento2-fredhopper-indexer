<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\Data\ExportFactory;
use Aligent\FredhopperExport\Model\Data\Files\CreateDirectory;
use Aligent\FredhopperExport\Model\Data\Files\CreateZipFile;
use Aligent\FredhopperExport\Model\Data\Files\GenerateProductFiles;
use Aligent\FredhopperExport\Model\Data\Files\GenerateVariantFiles;
use Aligent\FredhopperExport\Model\Data\GetCurrentExportedVersion;
use Aligent\FredhopperExport\Model\Data\IndexReplicaManagement;
use Aligent\FredhopperExport\Model\Data\Products\GetCompleteChangeList;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Aligent\FredhopperIndexer\Model\DataHandler;
use Aligent\FredhopperIndexer\Model\ResourceModel\Changelog;
use Psr\Log\LoggerInterface;

class GenerateIncrementalExport
{

    private const string ZIP_FILE_NAME = ExportInterface::ZIP_FILENAME_INCREMENTAL;

    /**
     * @param ExportFactory $exportFactory
     * @param ExportResource $exportResource
     * @param GetCurrentExportedVersion $getCurrentExportedVersion
     * @param GetCompleteChangeList $getCompleteChangeList
     * @param IndexReplicaManagement $indexReplicaManagement
     * @param Changelog $changelogResource
     * @param CreateDirectory $createDirectory
     * @param GenerateProductFiles $generateProductFiles
     * @param GenerateVariantFiles $generateVariantFiles
     * @param CreateZipFile $createZipFile
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ExportFactory $exportFactory,
        private readonly ExportResource $exportResource,
        private readonly GetCurrentExportedVersion $getCurrentExportedVersion,
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

    /**
     * Generate a partial product export
     *
     * @return void
     */
    public function execute(): void
    {

        $currentVersion = $this->getCurrentExportedVersion->execute();
        if ($currentVersion === 0) {
            // there is no recorded current version, so we cannot safely export a partial update
            return;
        }

        // create export entity
        /** @var Export $export */
        $export = $this->exportFactory->create();
        $export->setExportType(ExportInterface::EXPORT_TYPE_INCREMENTAL);

        // get changelist(s) and apply
        $changedProducts = $this->getCompleteChangeList->getList(DataHandler::TYPE_PRODUCT);
        $changedVariants = $this->getCompleteChangeList->getList(DataHandler::TYPE_VARIANT);

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

        try {
            // create replica of index table to work from
            // avoid index being updated while export is running
            $this->indexReplicaManagement->createReplicaTable();

            // create directory
            $directory = $this->createDirectory->execute(ExportInterface::EXPORT_TYPE_INCREMENTAL);
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

            $export->setStatus(ExportInterface::STATUS_PENDING);

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
