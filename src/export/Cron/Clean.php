<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Config\ExportConfig;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\Collection;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\CollectionFactory;
use Magento\Framework\Filesystem\Io\File;
use Psr\Log\LoggerInterface;

class Clean
{
    private const int SECONDS_PER_DAY = 60 * 60 * 24;

    /**
     * @param ExportConfig $exportConfig
     * @param CollectionFactory $collectionFactory
     * @param ExportResource $exportResource
     * @param File $fileSystem
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ExportConfig $exportConfig,
        private readonly CollectionFactory $collectionFactory,
        private readonly ExportResource $exportResource,
        private readonly File $fileSystem,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Remove old exports and their files from the system
     *
     * @return void
     */
    public function execute(): void
    {
        $exportRetentionDays = $this->exportConfig->getExportRetention();
        $startTime = time() - (self::SECONDS_PER_DAY * $exportRetentionDays);
        $startDate = date('Y-m-d H:i:s', $startTime);
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(ExportInterface::FIELD_CREATED_AT, ['lt' => $startDate]);
        /** @var Export[] $exports */
        $exports = $collection->getItems();
        foreach ($exports as $export) {
            $this->removeExport($export);
        }
    }

    /**
     * Remove export and related files
     *
     * @param Export $export
     * @return void
     */
    private function removeExport(Export $export): void
    {
        try {
            $directory = $export->getDirectory();
            $this->fileSystem->rmdir($directory, true);
            $this->exportResource->delete($export);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Error removing export %i', $export->getExportId()),
                ['exception' => $e]
            );
        }
    }
}
