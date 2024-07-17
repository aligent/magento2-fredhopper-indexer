<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Data\Process;

use Aligent\FredhopperCommon\Model\Config\AttributeConfig;
use Aligent\FredhopperCommon\Model\Data\AttributeDataProvider;
use Aligent\FredhopperIndexer\Model\Data\Product\GetProductEmulator;
use Aligent\FredhopperIndexer\Model\Data\Product\GetProductTypeInstance;
use Aligent\FredhopperIndexer\Model\Data\Product\VariantToProductMapping;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;

class PrepareProductIndex
{
    /**
     * @param AttributeDataProvider $attributeDataProvider
     * @param AttributeConfig $attributeConfig
     * @param GetProductTypeInstance $getProductTypeInstance
     * @param GetProductEmulator $getProductEmulator
     * @param ProductMapper $productMapper
     * @param VariantToProductMapping $variantToProductMapping
     */
    public function __construct(
        private readonly AttributeDataProvider $attributeDataProvider,
        private readonly AttributeConfig $attributeConfig,
        private readonly GetProductTypeInstance $getProductTypeInstance,
        private readonly GetProductEmulator $getProductEmulator,
        private readonly ProductMapper $productMapper,
        private readonly VariantToProductMapping $variantToProductMapping
    ) {
    }

    /**
     * Prepare the product index
     *
     * @throws LocalizedException
     */
    public function execute(array $productIndex, array $productData, int $storeId, array $additionalFields): array
    {
        // ensure product id is an integer
        $productId = (int)$productData['entity_id'];
        $typeId = $productData['type_id'];

        $index = [];

        foreach ($productIndex as $entityId => $attributeData) {
            foreach ($attributeData as $attributeId => $attributeValues) {
                $value = $this->attributeDataProvider->getAttributeValue($attributeValues);
                if ($value !== '') {
                    if (!isset($index[$attributeId])) {
                        $index[$attributeId] = [];
                    }
                    $index[$attributeId][$entityId] = $value;
                }
            }
        }

        /** @var Product $product */
        $product = $this->getProductEmulator->execute($productData['type_id']);
        $product->setId($productData['entity_id']);
        $product->setStoreId($storeId);

        $typeInstance = $this->getProductTypeInstance->execute($productData['type_id']);
        $data = $typeInstance->getSearchableData($product);
        if ($data) {
            $index['options'] = $data;
        }

        $indexData = $this->productMapper->mapProduct(
            $index,
            $productId,
            $storeId,
            $typeId,
            $additionalFields
        );
        $indexData = $this->populateBooleanAttributes($indexData);

        foreach ($indexData['variants'] as $variantId => $variantData) {
            $this->variantToProductMapping->addMapping($variantId, $productId);
        }
        return $indexData;
    }

    /**
     * Ensure boolean attributes are not ignored when false
     *
     * @throws LocalizedException
     */
    private function populateBooleanAttributes(array $indexData): array
    {
        // all boolean attributes are of type "int"
        $booleanAttributes = $this->attributeConfig->getBooleanAttributes();
        foreach ($booleanAttributes as $attribute) {
            if (!isset($indexData['product'][$attribute['attribute']])) {
                $indexData['product'][$attribute['attribute']] = '0';
            }
            foreach ($indexData['variants'] as  $variantId => $variantData) {
                if (!isset($variantData[$attribute['attribute']])) {
                    $indexData['variants'][$variantId][$attribute['attribute']] = '0';
                }
            }
        }
        return $indexData;
    }
}
