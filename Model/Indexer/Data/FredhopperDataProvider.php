<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;
use Magento\Framework\App\ResourceConnection;

class FredhopperDataProvider
{
    /**
     * @var ResourceConnection
     */
    protected $resource;
    /**
     * @var DataProvider
     */
    protected $searchDataProvider;
    /**
     * @var AdditionalFieldsProviderInterface
     */
    protected $additionalFieldsProvider;
    /**
     * @var Status
     */
    protected $catalogProductStatus;
    /**
     * @var AttributeConfig
     */
    protected $attributeConfig;
    /**
     * @var  ProductMapper
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
        ResourceConnection $resource,
        DataProvider $dataProvider,
        AdditionalFieldsProviderInterface $additionalFieldsProvider,
        Status $catalogProductStatus,
        AttributeConfig $attributeConfig,
        ProductMapper $productMapper,
        $batchSize = 500
    ) {
        $this->resource = $resource;
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
        $staticAttributes = $this->attributeConfig->getStaticAttributes();
        $products = $this->searchDataProvider->getSearchableProducts(
            $storeId,
            [],
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
            $statusAttribute = $this->searchDataProvider->getSearchableAttribute('status');
            $eavAttributesByType['int'][] = $statusAttribute->getAttributeId();
            $productsAttributes = $this->searchDataProvider->getProductAttributes(
                $storeId,
                $allProductIds,
                $eavAttributesByType
            );

            // Static field data are not included in searchDataProvider::getProductAttributes
            $this->addStaticAttributes($productsAttributes, $staticAttributes);

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
                [],
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

    /**
     * @param $products
     * @return array
     */
    protected function getRelatedProducts($products): array
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

    /**
     * @param $productId
     * @param array $productsAttributes
     * @return bool
     */
    protected function isProductEnabled($productId, array $productsAttributes): bool
    {
        $status = $this->searchDataProvider->getSearchableAttribute('status');
        $allowedStatuses = $this->catalogProductStatus->getVisibleStatusIds();
        return isset($productsAttributes[$productId][$status->getId()]) &&
            in_array($productsAttributes[$productId][$status->getId()], $allowedStatuses);
    }

    /**
     * @param array $productsAttributes
     * @param array $staticAttributes
     */
    protected function addStaticAttributes(array &$productsAttributes, array $staticAttributes): void
    {
        if (count($productsAttributes) == 0 || count($staticAttributes) == 0) {
            return;
        }
        $attributeIds = array_flip($staticAttributes);

        $conn = $this->resource->getConnection();
        $select = $conn->select()
            ->from($conn->getTableName('catalog_product_entity'), ['entity_id'])
            ->columns($staticAttributes)
            ->where('entity_id IN (?)', array_keys($productsAttributes));
        foreach ($conn->query($select) as $row) {
            $productId = $row['entity_id'];
            unset($row['entity_id']);
            foreach ($row as $col => $val) {
                $productsAttributes[$productId][$attributeIds[$col]] = $val;
            }
        }
    }

    /**
     * @param array $productIndex
     * @param array $productData
     * @param int $storeId
     * @param array $additionalFields
     * @return array
     */
    protected function prepareProductIndex(
        array $productIndex,
        array $productData,
        int $storeId,
        array $additionalFields
    ): array {
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

    /**
     * @param array $indexData
     * @return void
     */
    protected function populateBooleanAttributes(array &$indexData): void
    {
        // all boolean attributes are of type "int"
        $booleanAttributes = $this->attributeConfig->getBooleanAttributes();
        foreach ($booleanAttributes as $attribute) {
            if (!isset($indexData['product'][$attribute['attribute']])) {
                $indexData['product'][$attribute['attribute']] = '0';
            }
            foreach ($indexData['variants'] as &$variantData) {
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
