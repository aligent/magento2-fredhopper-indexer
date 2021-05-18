<?php
namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field\FHAttributeTypes;
use Aligent\FredhopperIndexer\Helper\AgeAttributeConfig;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;

class Products
{
    public const PRODUCT_TYPE_PRODUCT = 'p';
    public const PRODUCT_TYPE_VARIANT = 'v';

    protected const OPERATION_TYPE_ADD = 'add';
    protected const OPERATION_TYPE_UPDATE = 'update';
    protected const OPERATION_TYPE_REPLACE = 'replace';
    protected const OPERATION_TYPE_DELETE = 'delete';

    protected const OPERATION_TYPE_MAPPING = [
        DataHandler::OPERATION_TYPE_ADD => self::OPERATION_TYPE_ADD,
        DataHandler::OPERATION_TYPE_UPDATE => self::OPERATION_TYPE_UPDATE,
        DataHandler::OPERATION_TYPE_REPLACE => self::OPERATION_TYPE_REPLACE,
        DataHandler::OPERATION_TYPE_DELETE => self::OPERATION_TYPE_DELETE
    ];

    /**
     * @var \Aligent\FredhopperIndexer\Helper\GeneralConfig
     */
    protected $generalConfig;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\AttributeConfig
     */
    protected $attributeConfig;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\PricingAttributeConfig
     */
    protected $pricingAttributeConfig;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\StockAttributeConfig
     */
    protected $stockAttributeConfig;
    /**
     * @var AgeAttributeConfig
     */
    protected $ageAttributeConfig;
    /**
     * @var Meta
     */
    protected $metaData;
    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resource;
    /**
     * @var string[]
     */
    protected $siteVariantPriceAttributes = [
        'regular_price',
        'special_price',
        'min_price',
        'max_price'
    ];
    /**
     * @var string[]
     */
    protected $siteVariantStockAttributes = [
        'stock_qty',
        'stock_status'
    ];
    /**
     * @var string[]
     */
    protected $siteVariantImageAttributes = [
        '_imageurl',
        '_thumburl'
    ];
    /**
     * @var string[]
     */
    protected $siteVariantAgeAttributes = [
        'is_new',
        'days_online'
    ];
    /**
     * @var string[]
     */
    protected $siteVariantCustomAttributes = [];


    public function __construct(
        \Aligent\FredhopperIndexer\Helper\GeneralConfig $generalConfig,
        \Aligent\FredhopperIndexer\Helper\AttributeConfig $attributeConfig,
        \Aligent\FredhopperIndexer\Helper\PricingAttributeConfig $pricingAttributeConfig,
        \Aligent\FredhopperIndexer\Helper\StockAttributeConfig $stockAttributeConfig,
        \Aligent\FredhopperIndexer\Helper\AgeAttributeConfig $ageAttributeConfig,
        \Aligent\FredhopperIndexer\Model\Export\Data\Meta $metaData,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Magento\Framework\App\ResourceConnection $resource,
        $siteVariantPriceAttributes = [],
        $siteVariantStockAttributes = [],
        $siteVariantImageAttributes = [],
        $siteVariantAgeAttributes = [],
        $siteVariantCustomAttributes = []
    )
    {
        $this->generalConfig = $generalConfig;
        $this->attributeConfig = $attributeConfig;
        $this->pricingAttributeConfig = $pricingAttributeConfig;
        $this->stockAttributeConfig = $stockAttributeConfig;
        $this->ageAttributeConfig = $ageAttributeConfig;
        $this->metaData = $metaData;
        $this->json = $json;
        $this->resource = $resource;

        $this->siteVariantPriceAttributes = $this->pricingAttributeConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantPriceAttributes, $siteVariantPriceAttributes) : [];
        $this->siteVariantStockAttributes = $this->stockAttributeConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantStockAttributes, $siteVariantStockAttributes) : [];
        $this->siteVariantImageAttributes = $this->generalConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantImageAttributes, $siteVariantImageAttributes) : [];
        $this->siteVariantAgeAttributes = $this->ageAttributeConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantAgeAttributes, $siteVariantAgeAttributes) : [];
        $this->siteVariantCustomAttributes = $this->generalConfig->getUseSiteVariant() ?
            array_merge($this->siteVariantCustomAttributes, $siteVariantCustomAttributes) : [];
    }

    /**
     * @param bool $isIncremental
     * @return array
     */
    public function getProductData(bool $isIncremental)
    {
        return $this->getProcessedProductData($isIncremental);
    }

    /**
     * @param bool $isIncremental
     * @return array
     */
    public function getVariantData(bool $isIncremental)
    {
        return $this->getProcessedProductData($isIncremental, true);
    }

    /**
     * @param bool $isIncremental
     * @param bool $isVariants
     * @return array
     */
    protected function getProcessedProductData(bool $isIncremental, bool $isVariants = false)
    {
        $rawProductData = $this->getRawProductData($isIncremental, $isVariants);
        return $this->processProductData(
            $rawProductData,
            $isVariants,
            $isIncremental
        );
    }

    protected function getRawProductData(bool $isIncremental, bool $isVariants) : array
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
            ->where($isIncremental ? "operation_type is not null" : "ifnull(operation_type, '') <> 'd'")
            ->where("product_type = '{$productType}'");
        return $connection->fetchAll($select);
    }

    /**
     * Processes product data and returns array ready for export
     * @param array $rawProductData
     * @param bool $isVariants
     * @param bool $isIncremental
     * @return array
     */
    protected function processProductData(array $rawProductData, bool $isVariants, bool $isIncremental) : array
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
    protected function collateProductData(array $productData) {
        $productStoreData = [];
        foreach ($productData as $row) {
            $productStoreData[$row['product_id']] = $productStoreData[$row['product_id']] ?? [];
            $productStoreData[$row['product_id']]['stores'][$row['store_id']] =
                $this->json->unserialize($row['attribute_data']);
            $productStoreData[$row['product_id']]['parent_id'] = $row['parent_id'];
            $productStoreData[$row['product_id']]['operation_type'] = $row['operation_type'];
        }
        return $productStoreData;
    }

    protected function convertProductDataToFredhopperFormat(
        array $productStoreData,
        bool $isVariants,
        bool $isIncremental
    ) {
        $defaultLocale = $this->generalConfig->getDefaultLocale();
        $products = [];
        foreach ($productStoreData as $productId => $productData) {
            $product = [
                'product_id' => "{$this->generalConfig->getProductPrefix()}{$productId}",
                'attributes' => $this->convertAttributeDataToFredhopperFormat($productData, $defaultLocale),
                'locales' => [
                    $defaultLocale
                ]
            ];
            if ($isVariants) {
                $product['product_id'] = "{$this->generalConfig->getProductPrefix()}{$productData['parent_id']}";
                $product['variant_id'] = "{$this->generalConfig->getVariantPrefix()}{$productId}";
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
     * @param $productData
     * @param $defaultLocale
     * @return array
     */
    protected function convertAttributeDataToFredhopperFormat($productData, $defaultLocale)
    {
        $attributes = [];
        foreach ($productData['stores'] as $storeId => $storeData) {
            // convert to correct format for fredhopper export
            foreach ($storeData as $attributeCode => $attributeValues) {
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
                    case FHAttributeTypes::ATTRIBUTE_TYPE_HIERARCHICAL:
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
                        'value' => $value
                    ];
                    if ($addLocale) {
                        $valueEntry['locale'] = $defaultLocale;
                    }
                    $values[] = $valueEntry;
                }

                // will return attribute code with site variant if required
                // return false if non-site-variant attribute in non-default store
                $attributeId = $this->appendSiteVariantIfNecessary($attributeCode, $storeId);
                if ($attributeId) {
                    $attributes[] = [
                        'attribute_id' => $attributeId,
                        'values' => $values
                    ];
                }
            }
        }
        return $attributes;
    }

    /**
     * Returns the fredhopper attribute type for the given attribute code
     * Returns false is the type cannot be found
     * @param string $attributeCode
     * @return bool|string
     */
    protected function getAttributeFredhopperTypeByCode(string $attributeCode) {
        // categories attribute is hierarchical
        if ($attributeCode === 'categories') {
            return FHAttributeTypes::ATTRIBUTE_TYPE_HIERARCHICAL;
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
        // check metadata configuration for custom attributes
        foreach ($this->metaData->getCustomAttributeData() as $attributeData) {
            if ($attributeData['attribute_code'] === $attributeCode) {
                return $attributeData['fredhopper_type'];
            }
        }
        return $this->attributeConfig->getAttributesWithFredhopperType()[$attributeCode] ?? false;
    }

    /**
     * Returns attribute code with site variant appended if the attribute is configured to vary by site
     * Otherwise, returns unchanged code for default store, false for any other store
     * @param string $attributeCode
     * @param int $storeId
     * @return bool|string
     */
    protected function appendSiteVariantIfNecessary(string $attributeCode, int $storeId)
    {
        $defaultStoreId = $this->generalConfig->getDefaultStore();
        $siteVariantAttributes = $this->attributeConfig->getSiteVariantAttributes();
        if ($this->generalConfig->getUseSiteVariant()) {
            $siteVariant = $this->generalConfig->getSiteVariant($storeId);
            if (in_array($attributeCode, $siteVariantAttributes) ||
                in_array($attributeCode, $this->siteVariantStockAttributes) ||
                in_array($attributeCode, $this->siteVariantImageAttributes) ||
                in_array($attributeCode, $this->siteVariantAgeAttributes) ||
                in_array($attributeCode, $this->siteVariantCustomAttributes) ||
                !empty(array_filter($this->siteVariantPriceAttributes, function ($code) use ($attributeCode) {
                    return strpos($attributeCode, $code) === 0;
                }))) {
                return "{$attributeCode}_{$siteVariant}";
            }
        }
        // when not using store variants, only retain attributes in the default store
        return $storeId === (int)$defaultStoreId ? $attributeCode : false;
    }


}
