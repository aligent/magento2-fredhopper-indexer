<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Api\DataIntegrationClient;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Framework\Exception\AlreadyExistsException;

class TriggerDataLoad
{
    /**
     * @param DataIntegrationClient $dataIntegrationClient
     * @param ExportResource $exportResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly DataIntegrationClient $dataIntegrationClient,
        private readonly ExportResource $exportResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Trigger the given data id to load within Fredhopper
     *
     * @param ExportInterface $export
     * @return void
     */
    public function execute(ExportInterface $export): void
    {
        /** @var Export $export */
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
            try {
                $this->exportResource->save($export);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf('Error saving export %i', $export->getExportId()),
                    ['exception' => $e]
                );
            }
            return;
        }
        $export->setTriggerId($triggerId);
        $export->setDataStatus(ExportInterface::DATA_STATUS_UNKNOWN);
        try {
            $this->exportResource->save($export);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error saving export %i', $export->getExportId()),
                ['exception' => $e]
            );
        }
    }
}
