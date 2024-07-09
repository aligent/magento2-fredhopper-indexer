<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export\Data\Files;

use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;

class CreateDirectory
{
    /**
     * @param GeneralConfig $generalConfig
     * @param File $file
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly File $file
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
        $baseDirectory = $this->generalConfig->getExportDirectory();
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
