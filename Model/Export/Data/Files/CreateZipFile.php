<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Data\Files;

use Magento\Framework\Filesystem\Io\File;
use Psr\Log\LoggerInterface;

class CreateZipFile
{

    private File $file;
    private LoggerInterface $logger;

    public function __construct(
        File $file,
        LoggerInterface $logger
    ) {
        $this->file = $file;
        $this->logger = $logger;
    }

    /**
     * @param string $zipFilePath
     * @param array $files
     * @return bool
     */
    public function execute(string $zipFilePath, array $files): bool
    {
        $zipArchive = new \ZipArchive();
        if ($zipArchive->open($zipFilePath, \ZipArchive::CREATE) !== true) {
            $this->logger->critical("Error opening zip file $zipFilePath");
            return false;
        }

        foreach ($files as $filePath) {
            $pathInfo = $this->file->getPathInfo($filePath);
            if (!$zipArchive->addFile($filePath, $pathInfo['basename'])) {
                $this->logger->critical("Error adding file $filePath to zip file $zipFilePath");
                return false;
            }
        }
        if (!$zipArchive->close()) {
            $this->logger->critical("Error closing zip file $zipFilePath");
            return false;
        }
        return true;
    }
}
