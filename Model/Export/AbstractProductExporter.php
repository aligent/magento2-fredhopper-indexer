<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export;

use Aligent\FredhopperIndexer\Api\Export\ExporterInterface;
use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Aligent\FredhopperIndexer\Helper\Email;
use Aligent\FredhopperIndexer\Helper\SanityCheckConfig;
use Aligent\FredhopperIndexer\Model\Export\Data\Meta;
use Aligent\FredhopperIndexer\Model\Export\Data\Products;
use Aligent\FredhopperIndexer\Model\Export\Upload\FasUpload;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;
use Psr\Log\LoggerInterface;

abstract class AbstractProductExporter implements ExporterInterface
{
    protected const ZIP_FILE_FULL = 'data.zip';
    protected const ZIP_FILE_INCREMENTAL = 'data-incremental.zip';

    private const META_FILE = 'meta.json';
    private const PRODUCT_FILE_PREFIX = 'products-';
    private const VARIANT_FILE_PREFIX = 'variants-';

    protected LoggerInterface $logger;
    protected string $directory;

    private Products $products;
    private Meta $meta;
    private ZipFile $zipFile;
    private FasUpload $upload;
    private AttributeConfig $config;
    private SanityCheckConfig $sanityConfig;
    private Email $emailHelper;
    private File $filesystem;
    private Json $json;
    /**
     * @var string[]
     */
    private array $files = [];
    /**
     * @var int
     */
    private int $productLimit;

    private array $categoriesForValidation = [];
    private array $opCount = [];
    private array $deleteSkus = [];

    public function __construct(
        Products $products,
        Meta $meta,
        ZipFile $zipFile,
        FasUpload $upload,
        AttributeConfig $config,
        SanityCheckConfig $sanityConfig,
        Email $emailHelper,
        File $filesystem,
        Json $json,
        LoggerInterface $logger,
        int $productLimit = 1000
    ) {
        $this->products = $products;
        $this->meta = $meta;
        $this->zipFile = $zipFile;
        $this->upload = $upload;
        $this->config = $config;
        $this->sanityConfig = $sanityConfig;
        $this->emailHelper = $emailHelper;
        $this->filesystem = $filesystem;
        $this->json = $json;
        $this->logger = $logger;
        $this->productLimit = $productLimit;
    }

    abstract public function export(): bool;

    abstract protected function getDirectory() : string;

    abstract protected function getZipFileName() : string;

    /**
     * @param bool $isDryRun
     * @return void
     */
    public function setDryRun(bool $isDryRun): void
    {
        $this->upload->setDryRun($isDryRun);
    }

    /**
     * @param bool $isIncremental
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    protected function doExport(bool $isIncremental) : bool
    {
        // create a new temp directory for files to be sent to fredhopper
        $this->directory = $this->getDirectory();
        try {
            $this->filesystem->createDirectory($this->directory);
        } catch (\Exception $e) {
            $this->logger->critical(
                "Could not create directory $this->directory for export",
                ['exception' => $e]
            );
            return false;
        }

        if (!$isIncremental) {
            $metaContent = $this->generateMetaJson();
            if (!$metaContent) {
                return false;
            }
        }

        $productIds = $this->products->getAllProductIds($isIncremental);
        if (empty($productIds)) {
            $this->logger->info('Product export has no products to process - exiting.');
            return true;
        }

        $errs = [];
        $productCount = count($productIds);
        if (!$isIncremental) {
            $minProducts = $this->sanityConfig->getMinTotalProducts();
            if ($productCount < $minProducts) {
                $errs[] = "Full export has $productCount products, below minimum threshold of $minProducts";
            } else {
                $msg = "Generating JSON for full export of $productCount products (meets minimum of $minProducts)";
                $this->logger->info($msg);
            }
            $this->generateCategoryValidationArray($metaContent);
        }

        // generate JSON for export
        $fileIndex = 0;
        foreach (array_chunk($productIds, $this->productLimit) as $ids) {
            $productData = $this->products->getProductData($ids, $isIncremental);
            if (!$isIncremental) {
                // add product category information for minimum count validation
                $this->addProductsToCategoryCount($productData);
            } else {
                foreach ($productData as $product) {
                    $op = $product['operation'] ?? null;
                    if (!$op) {
                        continue;
                    }
                    $this->opCount[$op] = ($this->opCount[$op] ?? 0) + 1;

                    // Collate SKUs to delete for inclusion in logging
                    if ($op != 'delete') {
                        continue;
                    }
                    foreach ($product['attributes'] as $attr) {
                        if ($attr['attribute_id'] != 'sku') {
                            continue;
                        }
                        $value = reset($attr['values']);
                        if (isset($value['value'])) {
                            $this->deleteSkus[] = $value['value'];
                        }
                        break;
                    }
                }
            }
            if (!$this->generateProductsJson($productData, $fileIndex)) {
                return false;
            }
            $fileIndex++;
        }

        // ensure minimum category counts are met for full export
        if (!$isIncremental) {
            $errs = array_merge($errs, $this->validateCategories());
            if (count($errs) > 0) {
                foreach ($errs as $err) {
                    $this->logger->error($err);
                }
                $this->logger->critical("Cancelling export due to errors");
                $recipients = $this->sanityConfig->getErrorEmailRecipients();
                if (count($recipients) > 0) {
                    $this->emailHelper->sendErrorEmail($recipients, $errs);
                }
                return false;
            }
        } else {
            $msg = "Generating JSON for incremental export of $productCount products: ";
            $msg .= $this->json->serialize($this->opCount);
            $this->logger->info($msg);

            if (!empty($this->deleteSkus)) {
                $msg = "Deleted SKUs: " . implode(', ', array_slice($this->deleteSkus, 0, 10));
                if (count($this->deleteSkus) > 10) {
                    $msg .= ', ...';
                }
                $this->logger->info($msg);
            }
        }

        if ($this->config->getUseVariantProducts()) {
            $variantIds = $this->products->getAllVariantIds($isIncremental);
            if ($this->config->getDebugLogging()) {
                $this->logger->debug('Generating JSON for ' . count($variantIds) . ' variants');
            }
            $fileIndex = 0;
            foreach (array_chunk($variantIds, $this->productLimit) as $ids) {
                $variantData = $this->products->getVariantData($ids, $isIncremental);
                if (!$this->generateVariantsJson($variantData, $fileIndex)) {
                    return false;
                }
                $fileIndex++;
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
     * @return array[]|false
     * @throws LocalizedException
     */
    private function generateMetaJson()
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . self::META_FILE;
        $content = $this->meta->getMetaData();
        try {
            $this->filesystem->filePutContents($filePath, $this->json->serialize($content));
        } catch (\Exception $e) {
            // couldn't create a required file, so abort
            $this->logger->critical(
                "Error saving meta file $filePath",
                ['exception' => $e]
            );
            return false;
        }
        $this->files[] = $filePath;
        return $content;
    }

    /**
     * @return string[] Errors found in categories, if any
     */
    private function validateCategories(): array
    {
        $errors = [];

        $tierRequired = [
            1 => $this->sanityConfig->getMinProductsCategoryTier1(),
            2 => $this->sanityConfig->getMinProductsCategoryTier2(),
        ];

        // Ensure that tier 1 & 2 categories all have sufficient products to meet the tier's threshold
        $tierMin = [];
        $sufficientProducts = true;
        foreach ($this->categoriesForValidation as $cat) {
            $tier = $cat['tier'];
            if (!isset($tierMin[$tier]) || $cat['product_count'] < $tierMin[$tier]['product_count']) {
                $tierMin[$tier] = $cat;
            }

            $required = $tierRequired[$tier];
            if ($cat['product_count'] < $required) {
                $errMsg = "Insufficient products in tier $tier category {$cat['name']}";
                $errMsg .= ": {$cat['product_count']} (expected $required)";
                $errors[] = $errMsg;
                $sufficientProducts = false;
            }
        }

        if ($sufficientProducts) {
            foreach ($tierMin as $tier => $cat) {
                $msg = "Category {$cat['name']} has fewest products in tier $tier: {$cat['product_count']}";
                $this->logger->info($msg);
            }
        }

        return $errors;
    }

    /**
     * @param array $metaContent Format as per Data\Meta::getMetaData()
     * @return void
     */
    private function generateCategoryValidationArray(array $metaContent): void
    {
        $categories = [];
        // Collate tier 1 and 2 categories from FH meta-data structure into flat array
        foreach ($metaContent['meta']['attributes'] as $attr) {
            if ($attr['attribute_id'] == 'categories') {
                $catList = $attr['values'][0]['children'] ?? [];
                foreach ($catList as $category) {
                    $catId = (int)$category['category_id'];
                    $catName = $category['names'][0]['name'];
                    $categories[$catId] = [
                        'id' => $catId,
                        'name' => $catName,
                        'tier' => 1,
                        'parent' => null,
                        'product_count' => 0,
                    ];
                    foreach ($category['children'] as $child) {
                        $childCatId = (int)$child['category_id'];
                        $categories[$childCatId] = [
                            'id' => $childCatId,
                            'name' => $catName . ' > ' . $child['names'][0]['name'],
                            'tier' => 2,
                            'parent' => $catId,
                            'product_count' => 0,
                        ];
                    }
                }
                break;
            }
        }
        $this->categoriesForValidation = $categories;
    }

    /**
     * @param array $productData Format as per Data\Products::getProductData()
     * @return void
     */
    private function addProductsToCategoryCount(array $productData): void
    {
        // Count products in each tier 1/2 category
        foreach ($productData as $product) {
            foreach ($product['attributes'] as $attr) {
                if ($attr['attribute_id'] != 'categories') {
                    continue;
                }
                foreach ($attr['values'] as $productCategory) {
                    $catId = (int)$productCategory['value'];
                    if (!isset($this->categoriesForValidation[$catId])) {
                        continue;
                    }
                    $this->categoriesForValidation[$catId]['product_count'] += 1;
                }
            }
        }
    }

    /**
     * @param array $productData
     * @param int $fileIndex
     * @return bool
     */
    private function generateProductsJson(array $productData, int $fileIndex): bool
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . self::PRODUCT_FILE_PREFIX . $fileIndex . '.json';
        $content = ['products' => $productData];
        try {
            $this->filesystem->filePutContents($filePath, $this->json->serialize($content));
        } catch (\Exception $e) {
            $this->logger->critical(
                "Error saving products file $filePath",
                ['exception' => $e]
            );
            return false;
        }
        $this->files[] = $filePath;
        return true;
    }

    /**
     * @param array $variantData
     * @param int $fileIndex
     * @return bool
     */
    private function generateVariantsJson(array $variantData, int $fileIndex): bool
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . self::VARIANT_FILE_PREFIX . $fileIndex . '.json';
        $content = ['variants' => $variantData];
        try {
            $this->filesystem->filePutContents($filePath, $this->json->serialize($content));
        } catch (\Exception $e) {
            $this->logger->critical(
                "Error saving variants file $filePath",
                ['exception' => $e]
            );
            return false;
        }
        $this->files[] = $filePath;
        return true;
    }
}
