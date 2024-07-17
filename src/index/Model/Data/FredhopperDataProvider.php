<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Data;

use Aligent\FredhopperCommon\Model\Config\AttributeConfig;
use Aligent\FredhopperCommon\Model\Config\GeneralConfig;
use Aligent\FredhopperCommon\Model\Data\AttributeDataProvider;
use Aligent\FredhopperIndexer\Model\Data\Process\PrepareProductIndex;
use Aligent\FredhopperIndexer\Model\Data\Product\GetProductAttributes;
use Aligent\FredhopperIndexer\Model\Data\Product\GetProductChildIds;
use Aligent\FredhopperIndexer\Model\Data\Product\GetSearchableProducts;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\Exception\LocalizedException;

class FredhopperDataProvider
{

    /**
     * @param AdditionalFieldsProviderInterface $additionalFieldsProvider
     * @param AttributeDataProvider $attributeDataProvider
     * @param GeneralConfig $generalConfig
     * @param AttributeConfig $attributeConfig
     * @param GetSearchableProducts $getSearchableProducts
     * @param GetProductChildIds $getProductChildIds
     * @param GetProductAttributes $getProductAttributes
     * @param PrepareProductIndex $prepareProductIndex
     * @param int $batchSize
     */
    public function __construct(
        private readonly AdditionalFieldsProviderInterface $additionalFieldsProvider,
        private readonly AttributeDataProvider $attributeDataProvider,
        private readonly GeneralConfig $generalConfig,
        private readonly AttributeConfig $attributeConfig,
        private readonly GetSearchableProducts $getSearchableProducts,
        private readonly GetProductChildIds $getProductChildIds,
        private readonly GetProductAttributes $getProductAttributes,
        private readonly PrepareProductIndex $prepareProductIndex,
        private readonly int $batchSize = 500
    ) {
    }

    /**
     * Rebuilds product index for the given store
     *
     * @param int $storeId
     * @param array $productIds
     * @return \Generator
     * @throws LocalizedException
     * @throws \Exception
     */
    public function rebuildStoreIndex(int $storeId, array $productIds) : \Generator
    {
        // check if store is excluded from indexing
        if (in_array($storeId, $this->generalConfig->getExcludedStores())) {
            return;
        }
        $productIds = array_unique($productIds);

        $lastProductId = 0;
        $staticAttributes = $this->attributeConfig->getStaticAttributes();

        $products = $this->getSearchableProducts->execute(
            $storeId,
            $staticAttributes,
            $productIds,
            $lastProductId,
            $this->batchSize
        );

        while (count($products) > 0) {
            $allProductIds = array_column($products, 'entity_id');
            $relatedProducts = $this->getRelatedProducts($products);
            $relatedProductIds = [];
            foreach ($relatedProducts as $relatedArray) {
                $relatedProductIds[] = $relatedArray;
            }
            $allProductIds = array_merge($allProductIds, ...$relatedProductIds);

            // ensure that status attribute is always included
            $eavAttributesByType = $this->attributeConfig->getEavAttributesByType();
            $statusAttribute = $this->attributeDataProvider->getAttribute('status');
            $eavAttributesByType['int'][] = $statusAttribute->getAttributeId();
            $productsAttributes = $this->getProductAttributes->execute(
                $storeId,
                $allProductIds,
                $eavAttributesByType,
                $staticAttributes
            );

            // add any custom fields
            $additionalFields = $this->additionalFieldsProvider->getFields($allProductIds, $storeId);

            foreach ($products as $productData) {
                $lastProductId = (int)$productData['entity_id'];
                if (!isset($productsAttributes[$lastProductId])) {
                    continue;
                }
                $productIndex = [
                    $lastProductId => $productsAttributes[$lastProductId]
                ];
                if (isset($relatedProducts[$lastProductId])) {
                    $childProductsIndex = $this->getChildProductsIndex(
                        $lastProductId,
                        $relatedProducts,
                        $productsAttributes
                    );
                    $productIndex = $productIndex + $childProductsIndex;
                }
                $index = $this->prepareProductIndex->execute($productIndex, $productData, $storeId, $additionalFields);
                yield $lastProductId => $index;
            }
            $products = $this->getSearchableProducts->execute(
                $storeId,
                $staticAttributes,
                $productIds,
                $lastProductId,
                $this->batchSize
            );
        }
    }

    /**
     * Gets the child product index for a parent product
     *
     * @param int $parentId
     * @param array $relatedProducts
     * @param array $productAttributes
     * @return array
     * @throws LocalizedException
     */
    private function getChildProductsIndex(
        int $parentId,
        array $relatedProducts,
        array $productAttributes
    ) : array {
        $productIndex = [];

        foreach ($relatedProducts[$parentId] as $productChildId) {
            if ($this->isProductEnabled($productChildId, $productAttributes)) {
                $productIndex[$productChildId] = $productAttributes[$productChildId];
            }
        }
        return $productIndex;
    }

    /**
     * Get related products
     *
     * @param array $products
     * @return array
     * @throws \Exception
     */
    private function getRelatedProducts(array $products): array
    {
        $relatedProducts = [];
        foreach ($products as $productData) {
            $entityId = (int)$productData['entity_id'];
            $relatedProducts[$entityId] = $this->getProductChildIds->execute($entityId, $productData['type_id']);
        }
        return array_filter($relatedProducts);
    }

    /**
     * @param $productId
     * @param array $productsAttributes
     * @return bool
     * @throws LocalizedException
     */
    private function isProductEnabled($productId, array $productsAttributes): bool
    {
        $status = $this->attributeDataProvider->getAttribute('status');
        $allowedStatuses = [Status::STATUS_ENABLED];
        return isset($productsAttributes[$productId][$status->getId()]) &&
            in_array($productsAttributes[$productId][$status->getId()], $allowedStatuses);
    }
}
