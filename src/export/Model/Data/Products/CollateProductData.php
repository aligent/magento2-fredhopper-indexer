<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Products;

use Aligent\FredhopperCommon\Model\Config\GeneralConfig;
use Magento\Framework\Serialize\Serializer\Json;

class CollateProductData
{
    /**
     * @param Json $json
     * @param GeneralConfig $generalConfig
     */
    public function __construct(
        private readonly Json $json,
        private readonly GeneralConfig $generalConfig
    ) {
    }

    /**
     * Collect store records for each product into a single array
     *
     * @param array $productData
     * @return array
     */
    public function execute(array $productData): array
    {

        $defaultStore = $this->generalConfig->getDefaultStore();
        $productStoreData = [];

        foreach ($productData as $row) {
            $productStoreData[$row['product_id']] = $productStoreData[$row['product_id']] ?? [];
            $productStoreData[$row['product_id']]['stores'][$row['store_id']] =
                $this->json->unserialize($row['attribute_data']);
            $productStoreData[$row['product_id']]['parent_id'] = $row['parent_id'];
            $productStoreData[$row['product_id']]['operation_type'] = $row['operation_type'];
            // handle the case where a product does not belong to the default store
            if (!isset($productStoreData[$row['product_id']]['default_store']) || $row['store_id'] === $defaultStore) {
                $productStoreData[$row['product_id']]['default_store'] = (int)$row['store_id'];
            }
        }
        return $productStoreData;
    }
}
