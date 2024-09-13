<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Products;

use Aligent\FredhopperCommon\Block\Adminhtml\Form\Field\FHAttributeTypes;
use Aligent\FredhopperCommon\Model\Config\AttributeConfig;
use Aligent\FredhopperCommon\Model\Config\CustomAttributeConfig;
use Aligent\FredhopperCommon\Model\Config\GeneralConfig;
use Aligent\FredhopperIndexer\Model\DataHandler;

class ConvertToFredhopperFormat
{
    /**
     * @param GeneralConfig $generalConfig
     * @param AttributeConfig $attributeConfig
     * @param CustomAttributeConfig $customAttributeConfig
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly AttributeConfig $attributeConfig,
        private readonly CustomAttributeConfig $customAttributeConfig,
    ) {
    }

    /**
     * Convert product data into format required for Fredhopper JSON endpoint
     *
     * @param array $productStoreData
     * @param string $productType
     * @return array
     */
    public function execute(array $productStoreData, string $productType): array
    {
        $defaultLocale = $this->generalConfig->getDefaultLocale();
        $productPrefix = $this->generalConfig->getProductPrefix();
        $variantPrefix = $this->generalConfig->getVariantPrefix();
        $products = [];

        foreach ($productStoreData as $productId => $productData) {
            $defaultStore = $productData['default_store'];
            $product = [
                'product_id' => $productPrefix . $productId,
                'attributes' => $this->convertAttributeDataToFredhopperFormat(
                    $productData,
                    $defaultStore,
                    $defaultLocale,
                    $productType
                ),
                'locales' => [
                    $defaultLocale
                ]
            ];
            if ($productType === DataHandler::TYPE_VARIANT) {
                // replace the product id with the parent id, and set the variant id
                $product['product_id'] = $productPrefix . $productData['parent_id'];
                $product['variant_id'] = $variantPrefix . $productId;
            }
            if (isset($productData['operation'])) {
                $product['operation'] = $productData['operation'];
            }
            $products[] = $product;
        }
        return $products;
    }

    /**
     * Converts product attribute data from multiple stores into a single array in the correct format for fredhopper
     *
     * @param array $productData
     * @param int $defaultStore
     * @param string $defaultLocale
     * @param string $productType
     * @return array
     */
    private function convertAttributeDataToFredhopperFormat(
        array $productData,
        int $defaultStore,
        string $defaultLocale,
        string $productType
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
                    $attributeId = $this->mapAttributeId($attributeId);
                }
                if ($attributeId) {
                    $attributes[] = [
                        'attribute_id' => $attributeId,
                        'values' => $values
                    ];
                }
            }
        }
        // collate categories from all stores - only for products
        if ($productType === DataHandler::TYPE_PRODUCT) {
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
     *
     * Returns false is the type cannot be found
     * @param string $attributeCode
     * @return bool|string
     */
    private function getAttributeFredhopperTypeByCode(string $attributeCode): bool|string
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
        return $this->attributeConfig->getAttributesWithFredhopperType()[$attributeCode] ?? false;
    }

    /**
     * Returns attribute code with site variant
     *
     * Appended if the attribute is configured to vary by site
     * Otherwise, returns unchanged code for default store, false for any other store
     *
     * @param string $attributeCode
     * @param int $storeId
     * @param int $defaultStoreId
     * @return bool|string
     */
    private function appendSiteVariantIfNecessary(string $attributeCode, int $storeId, int $defaultStoreId): bool|string
    {
        $siteVariantAttributes = $this->attributeConfig->getSiteVariantAttributes();
        if ($this->generalConfig->getUseSiteVariant()) {
            $siteVariant = $this->generalConfig->getSiteVariant($storeId);
            if (in_array($attributeCode, $siteVariantAttributes) ||
                in_array($attributeCode, $this->customAttributeConfig->getSiteVariantCustomAttributes())) {
                return "{$attributeCode}_$siteVariant";
            }
        }
        // when not using store variants, only retain attributes in the default store
        return $storeId === $defaultStoreId ? $attributeCode : false;
    }

    /**
     * Map to Fredhopper attribute ID if required
     *
     * @param string $attributeId
     * @return string
     */
    private function mapAttributeId(string $attributeId): string
    {
        $attributeNameMapping = $this->attributeConfig->getAttributeNameMapping();
        return $attributeNameMapping[$attributeId] ?? $attributeId;
    }
}
