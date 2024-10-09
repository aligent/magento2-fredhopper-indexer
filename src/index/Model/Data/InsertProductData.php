<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Data;

use Aligent\FredhopperIndexer\Model\Data\Product\VariantToProductMapping;
use Aligent\FredhopperIndexer\Model\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

class InsertProductData
{
    /**
     * @param Json $json
     * @param ResourceConnection $resourceConnection
     * @param VariantToProductMapping $variantToProductMapping
     */
    public function __construct(
        private readonly Json $json,
        private readonly ResourceConnection $resourceConnection,
        private readonly VariantToProductMapping $variantToProductMapping
    ) {
    }

    /**
     * Insert data into scope index table
     *
     * @param string $tableName
     * @param array $products
     * @param array $variants
     * @param int $batchSize
     * @return void
     */
    public function execute(
        string $tableName,
        array $products,
        array $variants,
        int $batchSize
    ): void {
        $variantToProductMapping = $this->variantToProductMapping->getVariantToProductMapping();
        $productRows = [];
        foreach ($products as $productId => $attributeData) {
            $productRows[] = [
                'product_type' => DataHandler::TYPE_PRODUCT,
                'product_id' => $productId,
                'attribute_data' => $this->json->serialize($this->sortArray($attributeData))
            ];
        }

        $variantRows = [];
        foreach ($variants as $variantId => $attributeData) {
            $variantRows[] = [
                'product_type' => DataHandler::TYPE_VARIANT,
                'product_id' => $variantId,
                // dummy variants have themselves as parents
                'parent_id' => $variantToProductMapping[$variantId] ?? $variantId,
                'attribute_data' => $this->json->serialize($this->sortArray($attributeData))
            ];
        }

        foreach (array_chunk($productRows, $batchSize) as $batchRows) {
            $this->resourceConnection->getConnection()
                ->insertOnDuplicate($tableName, $batchRows, ['attribute_data']);
        }

        foreach (array_chunk($variantRows, $batchSize) as $batchRows) {
            $this->resourceConnection->getConnection()
                ->insertOnDuplicate($tableName, $batchRows, ['parent_id', 'attribute_data']);
        }
    }

    /**
     * Function to recursively sort an array by key (or value if keys are numeric) for ease of comparison by string
     *
     * @param array $array
     * @return array
     */
    private function sortArray(array $array) : array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->sortArray($value);
            }
        }
        reset($array);
        if (is_numeric(key($array))) {
            asort($array);
            $array = array_values($array); // reorder numeric keys
        } else {
            ksort($array);
        }
        return $array;
    }
}
