<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Api\DataIntegrationClient;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Framework\Exception\AlreadyExistsException;

readonly class TriggerDataLoad
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
     * Trigger the given data id to load within Fredhopper
     *
     * @param ExportInterface $export
     * @return void
     * @throws AlreadyExistsException
     */
    public function execute(ExportInterface $export): void
    {
        $exportType = $export->getExportType();
        $dataId = $export->getDataId();
        // cannot trigger data load without data ID
        if (empty($dataId)) {
            return;
        }
        if ($exportType === ExportInterface::EXPORT_TYPE_SUGGEST) {
            $triggerId = $this->dataIntegrationClient->triggerSuggestDataLoad($dataId);
        } else {
            $triggerId = $this->dataIntegrationClient->triggerFasDataLoad($dataId);
        }
        if (empty($triggerId)) {
            $export->setStatus(ExportInterface::STATUS_ERROR);
            $export->setError('Unable to trigger data load');
            $this->exportResource->save($export);
            return;
        }
        $export->setTriggerId($triggerId);
        $export->setDataStatus(ExportInterface::DATA_STATUS_UNKNOWN);
        $this->exportResource->save($export);
    }
}
