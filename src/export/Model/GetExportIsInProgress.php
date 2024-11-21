<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\Collection;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\CollectionFactory;

class GetExportIsInProgress
{
    private const array IN_PROGRESS_STATUSES = [
        ExportInterface::STATUS_UPLOADED,
        ExportInterface::STATUS_TRIGGERED
    ];

    /**
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {
    }

    /**
     * Check if there is already an export in uploaded/triggered status.
     *
     * If $triggeredOnly is true, only check for triggered status
     *
     * @param bool $triggeredOnly
     * @return bool
     */
    public function execute(bool $triggeredOnly = false): bool
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $statusesToCheck = $triggeredOnly ? [ExportInterface::STATUS_TRIGGERED] : self::IN_PROGRESS_STATUSES;
        $collection->addFieldToFilter('status', ['in' => self::IN_PROGRESS_STATUSES]);
        return $collection->getSize() > 0;
    }
}
