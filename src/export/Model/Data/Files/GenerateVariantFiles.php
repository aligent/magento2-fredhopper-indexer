<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Files;

use Aligent\FredhopperExport\Model\Data\Products\GetFredhopperProductData;
use Aligent\FredhopperIndexer\Model\DataHandler;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Serialize\Serializer\Json;

class GenerateVariantFiles
{

    private const int PRODUCT_LIMIT_PER_FILE = 1000;
    private const string VARIANT_FILE_PREFIX = 'variants-';

    /**
     * @param GetFredhopperProductData $getFredhopperProductData
     * @param Json $json
     * @param File $file
     */
    public function __construct(
        private readonly GetFredhopperProductData $getFredhopperProductData,
        private readonly Json $json,
        private readonly File $file
    ) {
    }

    /**
     * Generate variant files to export
     *
     * @param string $directory
     * @param array $allProductIds
     * @param bool $isIncremental
     * @return array
     * @throws FileSystemException
     */
    public function execute(string $directory, array $allProductIds, bool $isIncremental): array
    {
        $files = [];
        $fileNumber = 0;
        foreach (array_chunk($allProductIds, self::PRODUCT_LIMIT_PER_FILE) as $productIds) {
            $files[] = $this->generateFile($directory, $productIds, $isIncremental, $fileNumber);
            $fileNumber++;
        }
        return $files;
    }

    /**
     * Generates and saves JSON product file for the given IDs
     *
     * @param string $directory
     * @param array $productIds
     * @param bool $isIncremental
     * @param int $fileNumber
     * @return string
     * @throws FileSystemException
     */
    private function generateFile(string $directory, array $productIds, bool $isIncremental, int $fileNumber): string
    {
        $productData = $this->getFredhopperProductData->execute(
            $productIds,
            DataHandler::TYPE_VARIANT,
            $isIncremental
        );
        $filename = $directory . DIRECTORY_SEPARATOR . self::VARIANT_FILE_PREFIX . $fileNumber . '.json';
        $content = ['variants' => $productData];
        $this->file->filePutContents($filename, $this->json->serialize($content));
        return $filename;
    }
}
