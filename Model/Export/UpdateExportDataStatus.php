<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export;

use Aligent\FredhopperIndexer\Api\Export\Data\ExportInterface;
use Aligent\FredhopperIndexer\Model\Api\DataIntegrationClient;
use Aligent\FredhopperIndexer\Model\ResourceModel\Export\Data\Export as ExportResource;
use Magento\Framework\Exception\AlreadyExistsException;

class UpdateExportDataStatus
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
     * Update the data status of the given export
     *
     * @param ExportInterface $export
     * @return void
     * @throws AlreadyExistsException
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
        $this->exportResource->save($export);

        if ($dataStatus === ExportInterface::DATA_STATUS_UNKNOWN) {
            $export->setIsCurrent(true);
        }
    }
}
