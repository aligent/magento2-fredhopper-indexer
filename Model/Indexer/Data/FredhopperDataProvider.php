<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

class FredhopperDataProvider
{
    /**
     * @var \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider
     */
    protected $searchDataProvider;
    /**
     * @var \Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface
     */
    protected $additionalFieldsProvider;
    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status
     */
    protected $catalogProductStatus;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\AttributeConfig
     */
    protected $attributeConfig;
    /**
     * @var  \Aligent\FredhopperIndexer\Model\Indexer\Data\ProductMapper
     */
    protected $productMapper;
    /**
     * @var int
     */
    protected $batchSize;
    /**
     * @var array
     */
    protected $variantIdParentMapping = [];

    public function __construct(
        \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider $dataProvider,
        \Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface $additionalFieldsProvider,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $catalogProductStatus,
        \Aligent\FredhopperIndexer\Helper\AttributeConfig $attributeConfig,
        \Aligent\FredhopperIndexer\Model\Indexer\Data\ProductMapper $productMapper,
        $batchSize = 500
    ) {
        $this->searchDataProvider = $dataProvider;
        $this->additionalFieldsProvider = $additionalFieldsProvider;
        $this->catalogProductStatus = $catalogProductStatus;
        $this->attributeConfig = $attributeConfig;
        $this->productMapper = $productMapper;
        $this->batchSize = $batchSize;
    }

    public function rebuildStoreIndex($storeId, $productIds) : \Generator
    {
        if ($productIds !== null) {
            $productIds = array_unique($productIds);
        }

        $lastProductId = 0;
        $products = $this->searchDataProvider->getSearchableProducts(
            $storeId,
            $this->attributeConfig->getStaticAttributes(),
            $productIds,
            $lastProductId,
            $this->batchSize
        );

        while (count($products) > 0) {
            $allProductIds = array_column($products, 'entity_id');
            $relatedProducts = $this->getRelatedProducts($products);
            foreach ($relatedProducts as $productId => $relatedArray) {
                $allProductIds = array_unique(array_merge($allProductIds, $relatedArray));
            }

            // ensure that status attribute is always included
            $eavAttributesByType = $this->attributeConfig->getEavAttributesByType();
            $statusAttribute = $this->searchDataProvider->getSearchableAttribute('status');
            $eavAttributesByType['int'][] = $statusAttribute->getAttributeId();
            $productsAttributes = $this->searchDataProvider->getProductAttributes(
                $storeId,
                $allProductIds,
                $eavAttributesByType
            );

            $additionalFields = $this->additionalFieldsProvider->getFields($allProductIds, $storeId);

            foreach ($products as $productData) {
                $lastProductId = $productData['entity_id'];
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
                $index = $this->prepareProductIndex($productIndex, $productData, $storeId, $additionalFields);
                yield $lastProductId => $index;
            }
            $products = $this->searchDataProvider->getSearchableProducts(
                $storeId,
                $this->attributeConfig->getStaticAttributes(),
                $productIds,
                $lastProductId,
                $this->batchSize
            );
        }
    }

    protected function getChildProductsIndex(
        int $parentId,
        array $relatedProducts,
        array $productsAttributes
    ) : array {
        $productIndex = [];

        foreach ($relatedProducts[$parentId] as $productChildId) {
            if ($this->isProductEnabled($productChildId, $productsAttributes)) {
                $productIndex[$productChildId] = $productsAttributes[$productChildId];
            }
        }
        return $productIndex;
    }

    protected function getRelatedProducts($products)
    {
        $relatedProducts = [];
        foreach ($products as $productData) {
            $relatedProducts[$productData['entity_id']] = $this->searchDataProvider->getProductChildIds(
                $productData['entity_id'],
                $productData['type_id']
            );
        }
        return array_filter($relatedProducts);
    }

    protected function isProductEnabled($productId, array $productsAttributes)
    {
        $status = $this->searchDataProvider->getSearchableAttribute('status');
        $allowedStatuses = $this->catalogProductStatus->getVisibleStatusIds();
        return isset($productsAttributes[$productId][$status->getId()]) &&
            in_array($productsAttributes[$productId][$status->getId()], $allowedStatuses);
    }

    protected function prepareProductIndex(
        array $productIndex,
        array $productData,
        int $storeId,
        array $additionalFields
    ) {
        $productId = $productData['entity_id'];
        $typeId = $productData['type_id'];

        // first convert index to be based on attributes at top level, also converting values where necessary
        $index = $this->searchDataProvider->prepareProductIndex($productIndex, $productData, $storeId);

        // map attribute ids to attribute codes, get values for options
        $indexData = $this->productMapper->mapProduct(
            $index,
            $productId,
            $storeId,
            $typeId,
            $additionalFields
        );

        // boolean attributes with value of "No" (0) get removed by above functions - replace them here
        $this->populateBooleanAttributes($indexData);

        foreach ($indexData['variants'] as $variantId => $variantData) {
            $this->variantIdParentMapping[$variantId] = $productId;
        }
        return $indexData;
    }

    protected function populateBooleanAttributes(array &$indexData)
    {
        // all boolean attributes are of type "int"
        $booleanAttributes = $this->attributeConfig->getBooleanAttributes();
        foreach ($booleanAttributes as $attribute) {
            if (!isset($indexData['product'][$attribute['attribute']])) {
                $indexData['product'][$attribute['attribute']] = '0';
            }
            foreach ($indexData['variants'] as $variantId => &$variantData) {
                if (!isset($variantData[$attribute['attribute']])) {
                    $variantData[$attribute['attribute']] = '0';
                }
            }
        }
    }

    public function getVariantIdParentMapping() : array
    {
        return $this->variantIdParentMapping;
    }
}
