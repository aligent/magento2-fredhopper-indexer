<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\Collection;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\CollectionFactory;
use Aligent\FredhopperExport\Model\UploadExport;
use Magento\Framework\Exception\AlreadyExistsException;

class Upload
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param UploadExport $uploadExport
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly UploadExport $uploadExport
    ) {
    }

    /**
     * Upload pending exports
     *
     * @return void
     */
    public function execute(): void
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('status', ExportInterface::STATUS_PENDING);
        /** @var Export[] $exports */
        $exports = $collection->getItems();
        foreach ($exports as $export) {
            $this->uploadExport->execute($export);
        }
    }
}
