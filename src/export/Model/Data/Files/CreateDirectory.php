<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Files;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Config\DirectoryConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;

readonly class CreateDirectory
{
    /**
     * @param DirectoryConfig $directoryConfig
     * @param File $file
     */
    public function __construct(
        private DirectoryConfig $directoryConfig,
        private File $file
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
        $baseDirectory = $this->directoryConfig->getExportDirectory();
        $exportDirectory = $baseDirectory . $this->generateDirectoryName($exportType);
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
