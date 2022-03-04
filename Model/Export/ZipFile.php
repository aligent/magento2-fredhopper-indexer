<?php
namespace Aligent\FredhopperIndexer\Model\Export;

use Magento\Framework\Filesystem\Io\File;
use Psr\Log\LoggerInterface;

class ZipFile
{
    /**
     * @var File
     */
    protected $file;
    /**
     * @var LoggerInterface
     */
    protected $logger;

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
    public function createZipFile(string $zipFilePath, array $files): bool
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
