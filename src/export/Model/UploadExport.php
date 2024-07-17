<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Api\DataIntegrationClient;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;

class UploadExport
{

    /**
     * @param DataIntegrationClient $dataIntegrationClient
     * @param ExportResource $exportResource
     */
    public function __construct(
        private readonly DataIntegrationClient $dataIntegrationClient,
        private readonly ExportResource $exportResource
    ) {
    }

    /**
     * Uploads the given export data to Fredhopper
     *
     * @param ExportInterface $export
     * @return void
     * @throws AlreadyExistsException
     */
    public function execute(ExportInterface $export): void
    {
        try {
            $exportType = $export->getExportType();
            $filename = match ($exportType) {
                ExportInterface::EXPORT_TYPE_FULL => ExportInterface::ZIP_FILENAME_FULL,
                ExportInterface::EXPORT_TYPE_INCREMENTAL => ExportInterface::ZIP_FILENAME_INCREMENTAL,
                ExportInterface::EXPORT_TYPE_SUGGEST => ExportInterface::ZIP_FILENAME_SUGGEST,
                default => null
            };
            if ($filename === null) {
                throw new LocalizedException(__('Invalid export type'));
            }
            $zipFileName = $export->getDirectory() . $filename;
            if ($exportType === ExportInterface::EXPORT_TYPE_SUGGEST) {
                $dataId = $this->dataIntegrationClient->uploadSuggestData($zipFileName);
            } else {
                $dataId = $this->dataIntegrationClient->uploadFasData($zipFileName);
            }
            $export->setDataId($dataId);
            $export->setStatus(ExportInterface::STATUS_UPLOADED);
            $this->exportResource->save($export);
        } catch (\Exception $e) {
            $export->setStatus(ExportInterface::STATUS_ERROR);
            $export->setError($e->getMessage());
            $this->exportResource->save($export);
        }
    }
}
