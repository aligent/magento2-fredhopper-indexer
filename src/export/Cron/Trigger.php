<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\Collection;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\CollectionFactory;
use Aligent\FredhopperExport\Model\TriggerDataLoad;

class Trigger
{
    /**
     * @param CollectionFactory $exportCollectionFactory
     * @param TriggerDataLoad $triggerDataLoad
     */
    public function __construct(
        private readonly CollectionFactory $exportCollectionFactory,
        private readonly TriggerDataLoad $triggerDataLoad
    ) {
    }

    /**
     * Trigger loading of any uploaded exports (that have not yet been triggered)
     *
     * @return void
     */
    public function execute(): void
    {
        /** @var Collection $collection */
        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter(ExportInterface::FIELD_STATUS, ExportInterface::STATUS_UPLOADED);
        $collection->addFieldToFilter(ExportInterface::FIELD_DATA_STATUS, ['null' => true]);
        $collection->setOrder(ExportInterface::FIELD_EXPORT_ID);

        // we only ever want to trigger a single upload. Trying to trigger multiple at once could prove troublesome
        /** @var ExportInterface $firstExport */
        $firstExport = $collection->getFirstItem();
        if (!$firstExport->isEmpty()) {
            /** @var Export $export */
            $this->triggerDataLoad->execute($firstExport);
        }
    }
}
