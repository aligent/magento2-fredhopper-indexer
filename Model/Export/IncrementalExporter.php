<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export;

use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Aligent\FredhopperIndexer\Helper\Email;
use Aligent\FredhopperIndexer\Helper\SanityCheckConfig;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class IncrementalExporter extends AbstractProductExporter
{

    private DataHandler $dataHandler;

    public function __construct(
        Data\Products $products,
        Data\Meta $meta,
        ZipFile $zipFile,
        Upload\FasUpload $upload,
        AttributeConfig $config,
        SanityCheckConfig $sanityConfig,
        Email $emailHelper,
        File $filesystem,
        Json $json,
        LoggerInterface $logger,
        DataHandler $dataHandler,
        $productLimit = 1000
    ) {
        parent::__construct(
            $products,
            $meta,
            $zipFile,
            $upload,
            $config,
            $sanityConfig,
            $emailHelper,
            $filesystem,
            $json,
            $logger,
            $productLimit
        );
        $this->dataHandler = $dataHandler;
    }

    /**
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function export(): bool
    {
        $this->logger->info('Performing incremental data export');
        $success = $this->doExport(true);
        if ($success) {
            $success = $this->dataHandler->resetIndexAfterExport();
        }
        $this->logger->info('Incremental product export ' . ($success ? 'completed successfully' : 'failed'));
        return $success;
    }

    /**
     * @return string
     */
    protected function getDirectory(): string
    {
        if (!isset($this->directory)) {
            $this->directory = '/tmp/fh_export_incremental_' . time();
        }
        return $this->directory;
    }

    /**
     * @return string
     */
    protected function getZipFileName(): string
    {
        return self::ZIP_FILE_INCREMENTAL;
    }
}
