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
     * @param bool $isSummary
     * @return string|null
     */
    public function execute(string $directory, string $triggerId, bool $isSummary): ?string
    {
        $summaryFilename = $directory . DIRECTORY_SEPARATOR . 'quality_report.txt';
        $zipFilename = $directory . DIRECTORY_SEPARATOR . 'quality_report.gz';
        if ($isSummary) {
            $filename = $summaryFilename;
        } else {
            $filename = $zipFilename;
        }
        try {
            if ($this->file->isFile($filename)) {
                return $filename;
            }
        } catch (FileSystemException $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
        }

        $qualityReportData = $this->dataIntegrationClient->getDataQualityReport($triggerId, $isSummary);
        if ($qualityReportData === null) {
            return null;
        }
        try {
            $this->file->filePutContents($filename, $qualityReportData);
            return $filename;
        } catch (FileSystemException $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            return null;
        }
    }
}
