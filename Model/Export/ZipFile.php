<?php
namespace Aligent\FredhopperIndexer\Model\Export;

class ZipFile
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param string $zipFilePath
     * @param array $files
     * @return false|string
     */
    public function createZipFile(string $zipFilePath, array $files)
    {
        $zipArchive = new \ZipArchive();
        if ($zipArchive->open($zipFilePath, \ZipArchive::CREATE) !== true) {
            $this->logger->critical("Error opening zip file {$zipFilePath}");
            return false;
        }

        foreach ($files as $filePath) {
            if (!$zipArchive->addFile($filePath, basename($filePath))) {
                $this->logger->critical("Error adding file {$filePath} to zip file {$zipFilePath}");
                return false;
            }
        }
        if (!$zipArchive->close()) {
            $this->logger->critical("Error closing zip file {$zipFilePath}");
            return false;
        }
        return true;
    }
}
