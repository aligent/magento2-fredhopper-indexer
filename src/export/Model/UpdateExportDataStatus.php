<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Api\DataIntegrationClient;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Psr\Log\LoggerInterface;

class UpdateExportDataStatus
{

    /**
     * @param DataIntegrationClient $dataIntegrationClient
     * @param ExportResource $exportResource
     * @param SetCurrentExport $setCurrentExport
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly DataIntegrationClient $dataIntegrationClient,
        private readonly ExportResource $exportResource,
        private readonly SetCurrentExport $setCurrentExport,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Update the data status of the given export
     *
     * @param ExportInterface $export
     * @return void
     */
    public function execute(ExportInterface $export): void
    {
        $exportType = $export->getExportType();
        $triggerId = $export->getTriggerId();
        // cannot check status without a trigger ID
        if (empty($triggerId)) {
            return;
        }
        if ($exportType === ExportInterface::EXPORT_TYPE_SUGGEST) {
            $dataStatus = $this->dataIntegrationClient->getSuggestDataStatus($triggerId);
        } else {
            $dataStatus = $this->dataIntegrationClient->getFasDataStatus($triggerId);
        }
        if (in_array($dataStatus, ExportInterface::VALID_DATA_STATUSES)) {
            $export->setDataStatus($dataStatus);
        } else {
            $export->setDataStatus(ExportInterface::DATA_STATUS_UNKNOWN);
        }

        if ($dataStatus === ExportInterface::DATA_STATUS_FAILURE) {
            $export->setStatus(ExportInterface::STATUS_ERROR);
        } elseif ($dataStatus === ExportInterface::DATA_STATUS_SUCCESS) {
            $export->setStatus(ExportInterface::STATUS_COMPLETE);
        }
        try {
            $this->exportResource->save($export);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error saving export %i', $export->getExportId()),
                ['exception' => $e]
            );
        }

        if ($dataStatus === ExportInterface::DATA_STATUS_SUCCESS) {
            $this->setCurrentExport->execute($export->getExportId());
        }
    }
}
