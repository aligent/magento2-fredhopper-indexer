<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Model\Api\DataIntegrationClient;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Psr\Log\LoggerInterface;

class DownloadQualityReport
{
    /**
     * @param DataIntegrationClient $dataIntegrationClient
     * @param File $file
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly DataIntegrationClient $dataIntegrationClient,
        private readonly File $file,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Saves a copy of the quality report in the export directory
     *
     * @param string $directory
     * @param string $triggerId
     * @return string|null
     */
    public function execute(string $directory, string $triggerId): ?string
    {
        $filename = $directory . DIRECTORY_SEPARATOR . 'quality_report.txt';
        $qualityReportData = $this->dataIntegrationClient->getDataQualityReport($triggerId);
        try {
            $this->file->filePutContents($filename, $qualityReportData);
            return $filename;
        } catch (FileSystemException $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return null;
        }
    }
}
