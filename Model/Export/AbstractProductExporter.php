<?php
namespace Aligent\FredhopperIndexer\Model\Export;

abstract class AbstractProductExporter implements \Aligent\FredhopperIndexer\Api\Export\ExporterInterface
{
    protected const ZIP_FILE_FULL = 'data.zip';
    protected const ZIP_FILE_INCREMENTAL = 'data-incremental.zip';
    protected const META_FILE = 'meta.json';
    protected const PRODUCT_FILE_PREFIX = 'products-';
    protected const VARIANT_FILE_PREFIX = 'variants-';

    /**
     * @var Data\Products
     */
    protected $products;
    /**
     * @var Data\Meta
     */
    protected $meta;
    /**
     * @var ZipFile
     */
    protected $zipFile;
    /**
     * @var Upload\FasUpload
     */
    protected $upload;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\AttributeConfig
     */
    protected $config;
    /**
     * @var \Magento\Framework\Filesystem\Driver\File
     */
    protected $filesystem;
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var string
     */
    protected $directory;
    /**
     * @var string[]
     */
    protected $files = [];
    /**
     * @var int
     */
    protected $productLimit;

    public function __construct(
        \Aligent\FredhopperIndexer\Model\Export\Data\Products $products,
        \Aligent\FredhopperIndexer\Model\Export\Data\Meta $meta,
        \Aligent\FredhopperIndexer\Model\Export\ZipFile $zipFile,
        \Aligent\FredhopperIndexer\Model\Export\Upload\FasUpload $upload,
        \Aligent\FredhopperIndexer\Helper\AttributeConfig $config,
        \Magento\Framework\Filesystem\Driver\File $filesystem,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Psr\Log\LoggerInterface $logger,
        $productLimit = 1000
    ) {
        $this->products = $products;
        $this->meta = $meta;
        $this->zipFile = $zipFile;
        $this->upload = $upload;
        $this->config = $config;
        $this->filesystem = $filesystem;
        $this->json = $json;
        $this->logger = $logger;
        $this->productLimit = $productLimit;
    }

    public abstract function export(): bool;

    protected abstract function getDirectory() : string;

    protected abstract function getZipFileName() : string;

    public function setDryRun(bool $isDryRun): void
    {
        $this->upload->setDryRun($isDryRun);
    }

    protected function doExport(bool $isIncremental) : bool
    {
        // create a new temp directory for files to be sent to fredhopper
        $this->directory = $this->getDirectory();
        try {
            $this->filesystem->createDirectory($this->directory);
        } catch (\Exception $e) {
            $this->logger->critical(
                "Could not create directory {$this->directory} for export",
                ['exception' => $e]
            );
            return false;
        }

        if (!$isIncremental) {
            if (!$this->generateMetaJson()) {
                return false;
            }
        }
        $productData = $this->products->getProductData($isIncremental);
        if (empty($productData)) {
            $this->logger->info('Product export has no products to process - exiting.');
            return true;
        }
        if ($this->config->getDebugLogging()) {
            $this->logger->debug('Generating JSON for ' . count($productData) . ' products');
        }
        if (!$this->generateProductsJson($productData)) {
            return false;
        }
        if ($this->config->getUseVariantProducts()) {
            $variantData = $this->products->getVariantData($isIncremental);
            if ($this->config->getDebugLogging()) {
                $this->logger->debug('Generating JSON for ' . count($variantData) . ' variants');
            }
            if (!$this->generateVariantsJson($variantData)) {
                return false;
            }
        }

        $zipFilePath = $this->directory . DIRECTORY_SEPARATOR . $this->getZipFileName();
        // send and trigger update
        $success = $this->zipFile->createZipFile($zipFilePath, $this->files);
        if ($success) {
            $success = $this->upload->uploadZipFile($zipFilePath);
        }
        return $success;
    }

    /**
     * @return bool
     */
    protected  function generateMetaJson() : bool
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . self::META_FILE;
        $content = $this->meta->getMetaData();
        try {
            $this->filesystem->filePutContents($filePath, $this->json->serialize($content));
        } catch (\Exception $e) {
            // couldn't create a required file, so abort
            $this->logger->critical(
                "Error saving meta file {$filePath}",
                ['exception' => $e]
            );
            return false;
        }
        $this->files[] = $filePath;
        return true;
    }

    /**
     * @param array $productData
     * @return bool
     */
    protected function generateProductsJson(array $productData)
    {
        $fileIndex = 0;
        foreach (array_chunk($productData, $this->productLimit) as $products) {
            $filePath = $this->directory . DIRECTORY_SEPARATOR . self::PRODUCT_FILE_PREFIX . $fileIndex . '.json';
            $content = ['products' => $products];
            try {
                $this->filesystem->filePutContents($filePath, $this->json->serialize($content));
            } catch (\Exception $e) {
                $this->logger->critical(
                    "Error saving products file {$filePath}",
                    ['exception' => $e]
                );
                return false;
            }
            $this->files[] = $filePath;
            $fileIndex++;
        }
        return true;
    }

    /**
     * @param array $variantData
     * @return bool
     */
    protected function generateVariantsJson(array $variantData)
    {
        $fileIndex = 0;
        foreach (array_chunk($variantData, $this->productLimit) as $products) {
            $filePath = $this->directory . DIRECTORY_SEPARATOR . self::VARIANT_FILE_PREFIX . $fileIndex . '.json';
            $content = ['variants' => $products];
            try {
                $this->filesystem->filePutContents($filePath, $this->json->serialize($content));
            } catch (\Exception $e) {
                $this->logger->critical(
                    "Error saving variants file {$filePath}",
                    ['exception' => $e]
                );
                return false;
            }
            $this->files[] = $filePath;
            $fileIndex++;
        }
        return true;
    }
}
