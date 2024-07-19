<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\Collection;
use Aligent\FredhopperExport\Model\UpdateExportDataStatus;

class UpdateTriggeredExports
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param UpdateExportDataStatus $updateExportDataStatus
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly UpdateExportDataStatus $updateExportDataStatus
    )  {
    }

    /**
     * Update the data status of triggered exports
     *
     * @return void
     */
    public function execute(): void
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', ExportInterface::STATUS_TRIGGERED);
        /** @var Export[] $exports */
        $exports = $collection->getItems();
        foreach ($exports as $export) {
            $this->updateExportDataStatus->execute($export);
        }
    }
}
