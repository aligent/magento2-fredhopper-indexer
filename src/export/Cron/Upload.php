<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\Data\GetCurrentExportedVersion;
use Aligent\FredhopperExport\Model\GetExportIsInProgress;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\Collection;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\CollectionFactory;
use Aligent\FredhopperExport\Model\UploadExport;

class Upload
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param UploadExport $uploadExport
     * @param GetExportIsInProgress $getExportIsInProgress
     * @param GetCurrentExportedVersion $getCurrentExportedVersion
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly UploadExport $uploadExport,
        private readonly GetExportIsInProgress $getExportIsInProgress,
        private readonly GetCurrentExportedVersion $getCurrentExportedVersion
    ) {
    }

    /**
     * Upload pending exports
     *
     * @return void
     */
    public function execute(): void
    {
        // don't upload any export when an export is in progress
        if ($this->getExportIsInProgress->execute()) {
            return;
        }
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(ExportInterface::FIELD_STATUS, ExportInterface::STATUS_PENDING);
        // do not upload any export behind the current version
        $currentVersion = $this->getCurrentExportedVersion->execute();
        if ($currentVersion > 0) {
            $collection->addFieldToFilter(ExportInterface::FIELD_VERSION_ID, ['gt' => $currentVersion]);
        }
        // order by the version number descending to get the latest export
        $collection->setOrder(ExportInterface::FIELD_VERSION_ID);
        // we only ever want to upload a single export. Trying to upload multiple at once could prove troublesome
        /** @var ExportInterface $firstExport */
        $firstExport = $collection->getFirstItem();
        if (!$firstExport->isEmpty()) {
            /** @var Export $export */
            $this->uploadExport->execute($firstExport);
        }
    }
}
