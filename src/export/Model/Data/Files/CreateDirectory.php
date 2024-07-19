<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Files;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Config\ExportConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;

class CreateDirectory
{
    /**
     * @param ExportConfig $exportConfig
     * @param File $file
     * @param DirectoryList $directoryList
     */
    public function __construct(
        private readonly ExportConfig $exportConfig,
        private readonly File $file,
        private readonly DirectoryList $directoryList
    ) {
    }

    /**
     * Create a directory in the filesystem for the export
     *
     * @param string $exportType
     * @return string
     * @throws FileSystemException
     */
    public function execute(string $exportType): string
    {
        $rootDirectory = $this->directoryList->getRoot();
        $baseDirectory = $rootDirectory . DIRECTORY_SEPARATOR . $this->exportConfig->getExportDirectory();
        $exportDirectory = $baseDirectory . DIRECTORY_SEPARATOR . $this->generateDirectoryName($exportType);
        $this->file->createDirectory($exportDirectory);
        return $exportDirectory;
    }

    /**
     * Create a directory name based on the export type and current time
     *
     * @param string $exportType
     * @return string
     */
    private function generateDirectoryName(string $exportType): string
    {
        $type = match ($exportType) {
            ExportInterface::EXPORT_TYPE_FULL => 'full',
            ExportInterface::EXPORT_TYPE_INCREMENTAL => 'incremental',
            ExportInterface::EXPORT_TYPE_SUGGEST => 'suggest',
            default => ''
        };
        return 'fh_export_' . $type . '_' . time();
    }
}
