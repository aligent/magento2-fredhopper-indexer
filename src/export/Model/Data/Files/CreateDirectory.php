<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Files;

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
     * @param bool $isIncremental
     * @return string
     * @throws FileSystemException
     */
    public function execute(bool $isIncremental): string
    {
        $baseDirectory = $this->directoryConfig->getExportDirectory();
        $exportDirectory = $baseDirectory . $this->generateDirectoryName($isIncremental);
        $this->file->createDirectory($exportDirectory);
        return $exportDirectory;
    }


    private function generateDirectoryName(bool $isIncremental): string
    {
        // code to generate directory name
        return 'fh_export_' . ($isIncremental ? 'incremental' : '') . '_' . time();
    }
}
