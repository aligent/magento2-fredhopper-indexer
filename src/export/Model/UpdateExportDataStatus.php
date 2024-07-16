<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Api\DataIntegrationClient;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Framework\Exception\AlreadyExistsException;

readonly class UpdateExportDataStatus
{

    /**
     * @param DataIntegrationClient $dataIntegrationClient
     * @param ExportResource $exportResource
     */
    public function __construct(
        private DataIntegrationClient $dataIntegrationClient,
        private ExportResource $exportResource
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
