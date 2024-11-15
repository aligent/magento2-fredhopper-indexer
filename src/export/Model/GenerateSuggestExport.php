<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Api\FileGeneratorInterface;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\Data\ExportFactory;
use Aligent\FredhopperExport\Model\Data\Files\CreateDirectory;
use Aligent\FredhopperExport\Model\Data\Files\CreateZipFile;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

class GenerateSuggestExport
{
    private const string ZIP_FILE_NAME = ExportInterface::ZIP_FILENAME_SUGGEST;

    /**
     * @param ExportFactory $exportFactory
     * @param ExportResource $exportResource
     * @param CreateDirectory $createDirectory
     * @param CreateZipFile $createZipFile
     * @param FileGeneratorInterface[] $fileGenerators
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ExportFactory $exportFactory,
        private readonly ExportResource $exportResource,
        private readonly CreateDirectory $createDirectory,
        private readonly CreateZipFile $createZipFile,
        private readonly array $fileGenerators,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate a "suggest" export
     *
     * @return void
     */
    public function execute(): void
    {
        try {
            // create export entity
            /** @var Export $export */
            $export = $this->exportFactory->create();
            $export->setExportType(ExportInterface::EXPORT_TYPE_SUGGEST);
            $export->setStatus(ExportInterface::STATUS_PENDING);

            // create directory
            $directory = $this->createDirectory->execute(ExportInterface::EXPORT_TYPE_SUGGEST);

            // create all required files
            $files = [];
            foreach ($this->fileGenerators as $fileGenerator) {
                $file = $fileGenerator->generateFile($directory);
                if (!empty($file)) {
                    $files[] = $fileGenerator->generateFile($directory);
                }
            }

            // only create the zip file and export entity if there are files to include
            if (empty($files)) {
                $this->logger->info(__METHOD__ . ': No files to export');
                return;
            }

            // create zip file
            $zipFilePath = $directory . DIRECTORY_SEPARATOR . self::ZIP_FILE_NAME;
            $zipCreated = $this->createZipFile->execute(
                $zipFilePath,
                $files
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
