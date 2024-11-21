<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\Data\GetCurrentExportedVersion;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\Collection;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\CollectionFactory;
use Psr\Log\LoggerInterface;

class InvalidateExports
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param GetCurrentExportedVersion $getCurrentExportedVersion
     * @param ExportResource $exportResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly GetCurrentExportedVersion $getCurrentExportedVersion,
        private readonly ExportResource $exportResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Update the status of pending exports
     *
     * @return void
     */
    public function execute(): void
    {
        $currentExportedVersion = $this->getCurrentExportedVersion->execute();

        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        // need to check pending and uploaded exports only
        $collection->addFieldToFilter(ExportInterface::FIELD_STATUS, ExportInterface::STATUS_PENDING);
        $collection->addFieldToFilter(ExportInterface::FIELD_EXPORT_TYPE, ExportInterface::EXPORT_TYPE_INCREMENTAL);
        /** @var Export[] $exports */
        $exports = $collection->getItems();
        foreach ($exports as $export) {
            // mark incremental update as invalid if a more recent export has been uploaded already
            if ($currentExportedVersion > $export->getVersionId()) {
                $export->setStatus(ExportInterface::STATUS_INVALID);
                try {
                    $this->exportResource->save($export);
                } catch (\Exception $e) {
                    $message = sprintf('Error updating status of export %i', $export->getExportId());
                    $this->logger->error($message, ['exception' => $e]);
                }
            }
        }
    }
}
