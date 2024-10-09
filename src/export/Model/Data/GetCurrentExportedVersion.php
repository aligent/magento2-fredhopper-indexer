<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data;

use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\Collection;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\CollectionFactory;

class GetCurrentExportedVersion
{

    /**
     * @param CollectionFactory $exportCollectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $exportCollectionFactory
    ) {
    }

    /**
     * Get the version ID associated with the current data set in Fredhopper
     *
     * @return int
     */
    public function execute(): int
    {
        /** @var Collection $collection */
        $collection = $this->exportCollectionFactory->create();
        $collection->addFieldToFilter('is_current', true);
        /** @var Export $export */
        $export = $collection->getFirstItem();
        return $export->isEmpty() ? 0 : $export->getVersionId();
    }
}
