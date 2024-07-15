<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export;

use Aligent\FredhopperIndexer\Api\Export\Data\ExportInterface;
use Aligent\FredhopperIndexer\Model\Export\Data\Files\CreateDirectory;
use Aligent\FredhopperIndexer\Model\Export\Data\Export;
use Aligent\FredhopperIndexer\Model\Export\Data\Files\CreateZipFile;
use Aligent\FredhopperIndexer\Model\Export\Data\Files\GenerateMetaFile;
use Aligent\FredhopperIndexer\Model\Export\Data\GenerateProductFiles;
use Aligent\FredhopperIndexer\Model\Export\Data\GenerateVariantFiles;
use Aligent\FredhopperIndexer\Model\Export\Data\IndexReplicaManagement;
use Aligent\FredhopperIndexer\Model\Export\Data\Products\GetAllProductIds;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Aligent\FredhopperIndexer\Model\ResourceModel\Export\Data\Export as ExportResource;
use Aligent\FredhopperIndexer\Model\ResourceModel\Index\Changelog;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class GenerateFullExport
{
    private const ZIP_FILE_NAME = ExportInterface::ZIP_FILENAME_FULL;

    /**
     * @param ExportFactory $exportFactory
     * @param ExportResource $exportResource
     * @param GetAllProductIds $getAllProductIds
     * @param IndexReplicaManagement $indexReplicaManagement
     * @param Changelog $changelogResource
     * @param CreateDirectory $createDirectory
     * @param GenerateMetaFile $generateMetaFile
     * @param GenerateProductFiles $generateProductFiles
     * @param GenerateVariantFiles $generateVariantFiles
     * @param CreateZipFile $createZipFile
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ExportFactory $exportFactory,
        private readonly ExportResource $exportResource,
        private readonly GetAllProductIds $getAllProductIds,
        private readonly IndexReplicaManagement $indexReplicaManagement,
        private readonly Changelog $changelogResource,
        private readonly CreateDirectory $createDirectory,
        private readonly GenerateMetaFile $generateMetaFile,
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
            $export->setExportType(ExportInterface::EXPORT_TYPE_FULL);
            $export->setStatus(ExportInterface::STATUS_PENDING);

            // get id information
            $productIds = $this->getAllProductIds->execute(DataHandler::TYPE_PRODUCT);
            $variantIds = $this->getAllProductIds->execute(DataHandler::TYPE_VARIANT);
            $export->setProductCount(count($productIds));
            $export->setVariantCount(count($variantIds));

            $lastVersionId = $this->changelogResource->getLatestVersionId();
            $export->setVersionId($lastVersionId);

            // create replica of index table to work from
            // avoid index being updated while export is running
            $this->indexReplicaManagement->createReplicaTable();

            // create directory
            $directory = $this->createDirectory->execute(true);
            $export->setDirectory($directory);

            $metaFile = $this->generateMetaFile->execute($directory);
            // generate export files
            $productFiles = $this->generateProductFiles->execute(
                $directory,
                $productIds,
                false
            );

            $variantFiles = $this->generateVariantFiles->execute(
                $directory,
                $variantIds,
                false
            );

            // replica table is no longer needed
            $this->indexReplicaManagement->dropReplicaTable();

            // create zip file
            $zipFilePath = $directory . DIRECTORY_SEPARATOR . self::ZIP_FILE_NAME;
            $zipCreated = $this->createZipFile->execute(
                $zipFilePath,
                array_merge([$metaFile], $productFiles, $variantFiles)
            );
            if (!$zipCreated) {
                throw new LocalizedException(__('Error while creating ZIP file.'));
            }

            // save export
            $this->exportResource->save($export);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }
    }
}
