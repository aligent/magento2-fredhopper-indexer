<?php
namespace Aligent\FredhopperIndexer\Model\Export;

use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Aligent\FredhopperIndexer\Helper\Email;
use Aligent\FredhopperIndexer\Helper\SanityCheckConfig;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

class IncrementalExporter extends AbstractProductExporter
{

    /**
     * @var DataHandler
     */
    protected $dataHandler;

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
