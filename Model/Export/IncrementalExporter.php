<?php
namespace Aligent\FredhopperIndexer\Model\Export;

class IncrementalExporter extends AbstractProductExporter
{

    /**
     * @var \Aligent\FredhopperIndexer\Model\Indexer\DataHandler
     */
    protected $dataHandler;

    public function __construct(
        Data\Products $products,
        Data\Meta $meta,
        ZipFile $zipFile,
        Upload\FasUpload $upload,
        \Aligent\FredhopperIndexer\Helper\AttributeConfig $config,
        \Magento\Framework\Filesystem\Driver\File $filesystem,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Psr\Log\LoggerInterface $logger,
        \Aligent\FredhopperIndexer\Model\Indexer\DataHandler $dataHandler,
        $productLimit = 1000
    ) {
        parent::__construct(
            $products,
            $meta,
            $zipFile,
            $upload,
            $config,
            $filesystem,
            $json,
            $logger,
            $productLimit
        );
        $this->dataHandler = $dataHandler;
    }

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

    protected function getDirectory(): string
    {
        if (!$this->directory) {
            $this->directory = '/tmp/fh_export_incremental_' . time();
        }
        return $this->directory;
    }

    protected function getZipFileName(): string
    {
        return self::ZIP_FILE_INCREMENTAL;
    }
}
