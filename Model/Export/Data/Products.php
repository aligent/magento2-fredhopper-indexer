<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field\FHAttributeTypes;
use Aligent\FredhopperIndexer\Helper\AgeAttributeConfig;
use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Aligent\FredhopperIndexer\Helper\CustomAttributeConfig;
use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Aligent\FredhopperIndexer\Helper\ImageAttributeConfig;
use Aligent\FredhopperIndexer\Helper\PricingAttributeConfig;
use Aligent\FredhopperIndexer\Helper\StockAttributeConfig;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

class Products
{
    public const PRODUCT_TYPE_PRODUCT = 'p';
    public const PRODUCT_TYPE_VARIANT = 'v';

    private const OPERATION_TYPE_ADD = 'add';
    private const OPERATION_TYPE_UPDATE = 'update';
    private const OPERATION_TYPE_REPLACE = 'replace';
    private const OPERATION_TYPE_DELETE = 'delete';

    private const OPERATION_TYPE_MAPPING = [
        DataHandler::OPERATION_TYPE_ADD => self::OPERATION_TYPE_ADD,
        DataHandler::OPERATION_TYPE_UPDATE => self::OPERATION_TYPE_UPDATE,
        DataHandler::OPERATION_TYPE_REPLACE => self::OPERATION_TYPE_REPLACE,
        DataHandler::OPERATION_TYPE_DELETE => self::OPERATION_TYPE_DELETE
    ];

    private GeneralConfig $generalConfig;
    private AttributeConfig $attributeConfig;
    private PricingAttributeConfig $pricingAttributeConfig;
    private StockAttributeConfig $stockAttributeConfig;
    private AgeAttributeConfig $ageAttributeConfig;
    private ImageAttributeConfig $imageAttributeConfig;
    private CustomAttributeConfig $customAttributeConfig;
    private Json $json;
    private ResourceConnection $resource;
    /**
     * @var string[]
     */
    private array $siteVariantPriceAttributes = [
        'regular_price',
        'special_price',
        'min_price',
        'max_price'
    ];
    /**
     * @var string[]
     */
    private array $siteVariantStockAttributes = [
        'stock_qty',
        'stock_status'
    ];
    /**
     * @var string[]
     */
    private array $siteVariantImageAttributes = [
        '_imageurl',
        '_thumburl'
    ];
    /**
     * @var string[]
     */
    private array $siteVariantAgeAttributes = [
        'is_new',
        'days_online'
    ];

    public function __construct(
        GeneralConfig $generalConfig,
        AttributeConfig $attributeConfig,
        PricingAttributeConfig $pricingAttributeConfig,
        StockAttributeConfig $stockAttributeConfig,
        AgeAttributeConfig $ageAttributeConfig,
        ImageAttributeConfig $imageAttributeConfig,
        CustomAttributeConfig $customAttributeConfig,
        Json $json,
        ResourceConnection $resource,
        $siteVariantPriceAttributes = [],
        $siteVariantStockAttributes = [],
        $siteVariantImageAttributes = [],
        $siteVariantAgeAttributes = []
    ) {
        $this->generalConfig = $generalConfig;
        $this->attributeConfig = $attributeConfig;
        $this->pricingAttributeConfig = $pricingAttributeConfig;
        $this->stockAttributeConfig = $stockAttributeConfig;
        $this->ageAttributeConfig = $ageAttributeConfig;
        $this->imageAttributeConfig = $imageAttributeConfig;
        $this->customAttributeConfig = $customAttributeConfig;
        $this->json = $json;
        $this->resource = $resource;

        $this->siteVariantPriceAttributes = $this->pricingAttributeConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantPriceAttributes, $siteVariantPriceAttributes) : [];
        $this->siteVariantStockAttributes = $this->stockAttributeConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantStockAttributes, $siteVariantStockAttributes) : [];
        $this->siteVariantImageAttributes = $this->imageAttributeConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantImageAttributes, $siteVariantImageAttributes) : [];
        $this->siteVariantAgeAttributes = $this->ageAttributeConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantAgeAttributes, $siteVariantAgeAttributes) : [];
    }

    /**
     * @param bool $isIncremental
     * @return array
     */
    public function getAllProductIds(bool $isIncremental): array
    {
        return $this->getAllIds($isIncremental, false);
    }

    /**
     * @param bool $isIncremental
     * @return array
     */
    public function getAllVariantIds(bool $isIncremental): array
    {
        return $this->getAllIds($isIncremental, true);
    }

    /**
     * @param bool $isIncremental
     * @param bool $isVariants
     * @return array
     */
    private function getAllIds(bool $isIncremental, bool $isVariants): array
    {
        $productType = $isVariants ? self::PRODUCT_TYPE_VARIANT : self::PRODUCT_TYPE_PRODUCT;
        $connection = $this->resource->getConnection();
        $select = $connection->select();
        $select->from(
            DataHandler::INDEX_TABLE_NAME,
            ['product_id' => 'product_id']
        );
        $select->where("product_type = ?", $productType);
        if ($isIncremental) {
            $select->where('operation_type is not null');
        } else {
            $select->where("ifnull(operation_type, '') <> 'd'");
        }
        $select->distinct();

        return $connection->fetchCol($select);
    }

    /**
     * @param array $productIds
     * @param bool $isIncremental
     * @return array
     */
    public function getProductData(array $productIds, bool $isIncremental): array
    {
        return $this->getProcessedProductData($productIds, $isIncremental);
    }

    /**
     * @param array $productIds
     * @param bool $isIncremental
     * @return array
     */
    public function getVariantData(array $productIds, bool $isIncremental): array
    {
        return $this->getProcessedProductData($productIds, $isIncremental, true);
    }

    /**
     * @param array $productIds
     * @param bool $isIncremental
     * @param bool $isVariants
     * @return array
     */
    private function getProcessedProductData(array $productIds, bool $isIncremental, bool $isVariants = false): array
    {
        $rawProductData = $this->getRawProductData($productIds, $isIncremental, $isVariants);
        return $this->processProductData(
            $rawProductData,
            $isVariants,
            $isIncremental
        );
    }

    /**
     * @param array $productIds
     * @param bool $isIncremental
     * @param bool $isVariants
     * @return array
     */
    protected function getRawProductData(array $productIds, bool $isIncremental, bool $isVariants) : array
    {
        $productType = $isVariants ? self::PRODUCT_TYPE_VARIANT : self::PRODUCT_TYPE_PRODUCT;
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(DataHandler::INDEX_TABLE_NAME)
            ->columns([
                          'store_id' => 'store_id',
                          'product_type' => 'product_type',
                          'product_id' => 'product_id',
                          'parent_id' => 'parent_id',
                          'attribute_data' => 'attribute_data'
                      ])
            ->where("product_type = ?", $productType)
            ->where('product_id in (?)', $productIds);
        if ($isIncremental) {
            $select->where('operation_type is not null');
        } else {
            $select->where("ifnull(operation_type, '') <> 'd'");
        }

        return $connection->fetchAll($select);
    }

    /**
     * Processes product data and returns array ready for export
     * @param array $rawProductData
     * @param bool $isVariants
     * @param bool $isIncremental
     * @return array
     */
    private function processProductData(array $rawProductData, bool $isVariants, bool $isIncremental) : array
    {
        // collect store records for each product into a single array
        $productStoreData = $this->collateProductData($rawProductData);

        return $this->convertProductDataToFredhopperFormat(
            $productStoreData,
            $isVariants,
            $isIncremental
        );
    }

    /**
     * Combines all database records for each product into a single array.
     * @param array $productData
     * @return array
     */
    private function collateProductData(array $productData): array
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

    /**
     * @param array $productStoreData
     * @param bool $isVariants
     * @param bool $isIncremental
     * @return array
     */
    private function convertProductDataToFredhopperFormat(
        array $productStoreData,
        bool $isVariants,
        bool $isIncremental
    ): array {
        $defaultLocale = $this->generalConfig->getDefaultLocale();
        $products = [];
        foreach ($productStoreData as $productId => $productData) {
            $defaultStore = $productData['default_store'];
            $product = [
                'product_id' => "{$this->generalConfig->getProductPrefix()}$productId",
                'attributes' => $this->convertAttributeDataToFredhopperFormat(
                    $productData,
                    $defaultStore,
                    $defaultLocale,
                    $isVariants
                ),
                'locales' => [
                    $defaultLocale
                ]
            ];
            if ($isVariants) {
                $product['product_id'] = "{$this->generalConfig->getProductPrefix()}{$productData['parent_id']}";
                $product['variant_id'] = "{$this->generalConfig->getVariantPrefix()}$productId";
            }
            if ($isIncremental) {
                $product['operation'] = self::OPERATION_TYPE_MAPPING[$productData['operation_type']];
            }
            $products[] = $product;
        }
        return $products;
    }

    /**
     * Converts product attribute data from multiple stores into a single array in the correct format for fredhopper
     * @param array $productData
     * @param int $defaultStore
     * @param string $defaultLocale
     * @param bool $isVariants
     * @return array
     */
    private function convertAttributeDataToFredhopperFormat(
        array $productData,
        int $defaultStore,
        string $defaultLocale,
        bool $isVariants
    ): array {
        $attributes = [];
        $categories = [];
        foreach ($productData['stores'] as $storeId => $storeData) {
            // convert to correct format for fredhopper export
            foreach ($storeData as $attributeCode => $attributeValues) {
                // handle categories separately
                if ($attributeCode === 'categories') {
                    $categories[] = $attributeValues;
                    continue;
                }
                if (!is_array($attributeValues)) {
                    $attributeValues = [$attributeValues];
                }
                $addLocale = false;
                $fredhopperType = $this->getAttributeFredhopperTypeByCode($attributeCode);
                // for "localisable" types, need to add locale information
                switch ($fredhopperType) {
                    case FHAttributeTypes::ATTRIBUTE_TYPE_LIST:
                    case FHAttributeTypes::ATTRIBUTE_TYPE_LIST64:
                    case FHAttributeTypes::ATTRIBUTE_TYPE_SET:
                    case FHAttributeTypes::ATTRIBUTE_TYPE_SET64:
                    case FHAttributeTypes::ATTRIBUTE_TYPE_ASSET:
                        // add locale to attribute data
                        $addLocale = true;
                        break;
                    case FHAttributeTypes::ATTRIBUTE_TYPE_INT:
                    case FHAttributeTypes::ATTRIBUTE_TYPE_FLOAT:
                    case FHAttributeTypes::ATTRIBUTE_TYPE_TEXT:
                        break;
                    default:
                        // invalid attribute type - remove from array
                        unset($storeData[$attributeCode]);
                        continue 2;
                }

                $values = [];
                foreach ($attributeValues as $value) {
                    $valueEntry = [
                        'value' => (string)$value // ensure all values are strings
                    ];
                    if ($addLocale) {
                        $valueEntry['locale'] = $defaultLocale;
                    }
                    $values[] = $valueEntry;
                }

                // will return attribute code with site variant if required
                // return false if non-site-variant attribute in non-default store
                $attributeId = $this->appendSiteVariantIfNecessary($attributeCode, $storeId, $defaultStore);
                if ($attributeId) {
                    $attributes[] = [
                        'attribute_id' => $attributeId,
                        'values' => $values
                    ];
                }
            }
        }
        // collate categories from all stores - only for products
        if (!$isVariants) {
            $categories = array_unique(array_merge(...$categories));
            $categoryValues = [];
            foreach ($categories as $category) {
                $categoryValues[] = [
                    'value' => (string)$category,
                    'locale' => $defaultLocale
                ];
            }
            $attributes[] = [
                'attribute_id' => 'categories',
                'values' => $categoryValues
            ];
        }
        return $attributes;
    }

    /**
     * Returns the fredhopper attribute type for the given attribute code
     * Returns false is the type cannot be found
     * @param string $attributeCode
     * @return bool|string
     */
    private function getAttributeFredhopperTypeByCode(string $attributeCode)
    {
        // categories attribute is hierarchical
        if ($attributeCode === 'categories') {
            return FHAttributeTypes::ATTRIBUTE_TYPE_HIERARCHICAL;
        }
        // check custom attribute configuration
        foreach ($this->customAttributeConfig->getCustomAttributeData() as $attributeData) {
            if ($attributeData['attribute_code'] === $attributeCode) {
                return $attributeData['fredhopper_type'];
            }
        }
        // all price attributes are floats
        if (strpos($attributeCode, 'price') !== false) {
            return FHAttributeTypes::ATTRIBUTE_TYPE_FLOAT;
        }
        // all stock and age attributes are ints (boolean -> 1/0 for indicators)
        if (strpos($attributeCode, 'stock') !== false ||
            $attributeCode === 'is_new' || $attributeCode === 'days_online') {
            return FHAttributeTypes::ATTRIBUTE_TYPE_INT;
        }
        // all url attributes are assets
        if (strpos($attributeCode, 'url') !== false) {
            return FHAttributeTypes::ATTRIBUTE_TYPE_ASSET;
        }
        return $this->attributeConfig->getAttributesWithFredhopperType()[$attributeCode] ?? false;
    }

    /**
     * Returns attribute code with site variant appended if the attribute is configured to vary by site
     * Otherwise, returns unchanged code for default store, false for any other store
     * @param string $attributeCode
     * @param int $storeId
     * @param int $defaultStoreId
     * @return bool|string
     */
    private function appendSiteVariantIfNecessary(string $attributeCode, int $storeId, int $defaultStoreId)
    {
        $siteVariantAttributes = $this->attributeConfig->getSiteVariantAttributes();
        if ($this->generalConfig->getUseSiteVariant()) {
            $siteVariant = $this->generalConfig->getSiteVariant($storeId);
            if (in_array($attributeCode, $siteVariantAttributes) ||
                in_array($attributeCode, $this->siteVariantStockAttributes) ||
                in_array($attributeCode, $this->siteVariantImageAttributes) ||
                in_array($attributeCode, $this->siteVariantAgeAttributes) ||
                in_array($attributeCode, $this->customAttributeConfig->getSiteVariantCustomAttributes()) ||
                $this->isSiteVariantPriceAttribute($attributeCode)) {
                return "{$attributeCode}_$siteVariant";
            }
        }
        // when not using store variants, only retain attributes in the default store
        return $storeId === $defaultStoreId ? $attributeCode : false;
    }

    /**
     * Price attributes may have a suffix (e.g. regular_price_min), so use strpos for comparison
     * @param string $attributeCode
     * @return bool
     */
    private function isSiteVariantPriceAttribute(string $attributeCode): bool
    {
        foreach ($this->siteVariantPriceAttributes as $code) {
            if (strpos($attributeCode, $code) === 0) {
                return true;
            }
        }
        return false;
    }
}
