<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export;

use Aligent\FredhopperIndexer\Api\Export\Data\ExportInterface;
use Aligent\FredhopperIndexer\Model\Api\DataIntegrationClient;
use Aligent\FredhopperIndexer\Model\ResourceModel\Export\Data\Export as ExportResource;
use Magento\Framework\Exception\AlreadyExistsException;

class TriggerDataLoad
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
