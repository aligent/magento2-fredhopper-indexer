<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Model\Config;

use Aligent\FredhopperCommon\Model\Data\AttributeDataProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

class AttributeConfig
{
    public const string XML_PATH_PREFIX = 'fredhopper_indexer/product_config/';
    public const string XML_PATH_USE_VARIANT_PRODUCTS = self::XML_PATH_PREFIX . 'use_variant_products';
    public const string XML_PATH_PRODUCT_ATTRIBUTES = self::XML_PATH_PREFIX . 'product_attributes';
    public const string XML_PATH_VARIANT_ATTRIBUTES = self::XML_PATH_PREFIX . 'variant_attributes';
    public const string XML_PATH_ATTRIBUTE_MAPPING = self::XML_PATH_PREFIX . 'attribute_mapping';

    /**
     * @var array
     */
    private array $productAttributes;
    /**
     * @var array
     */
    private array $variantAttributes;
    /**
     * @var array
     */
    private array $allAttributes;
    /**
     * @var array
     */
    private array $booleanAttributes;
    /**
     * @var array
     */
    private array $productAttributeCodes;
    /**
     * @var array
     */
    private array $variantAttributeCodes;
    /**
     * @var array
     */
    private array $staticAttributes;
    /**
     * @var array
     */
    private array $attributesWithFredhopperType;
    /**
     * @var array
     */
    private array $eavAttributesByType;
    /**
     * @var array
     */
    private array $siteVariantAttributes;
    /**
     * @var array
     */
    private array $attributeNameMapping;

    /**
     * @param GeneralConfig $generalConfig
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $json
     * @param CustomAttributeConfig $customAttributeConfig
     * @param AttributeDataProvider $attributeDataProvider
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json,
        private readonly CustomAttributeConfig $customAttributeConfig,
        private readonly AttributeDataProvider $attributeDataProvider
    ) {
    }

    /**
     * Gets whether to use variant products
     *
     * @return bool
     */
    public function getUseVariantProducts(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_VARIANT_PRODUCTS);
    }

    /**
     * Get all configured product attributes
     *
     * @return array
     */
    public function getProductAttributes(): array
    {
        if (!isset($this->productAttributes)) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_PRODUCT_ATTRIBUTES);
            $productAttributes = $this->json->unserialize($configValue ?? '[]') ?? [];
            $productAttributes = $this->addMagentoAttributeData($productAttributes);
            $this->productAttributes = $productAttributes;
        }
        return $this->productAttributes;
    }

    /**
     * Get all configured variant attributes
     *
     * @return array
     */
    public function getVariantAttributes(): array
    {
        if (!isset($this->variantAttributes)) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_VARIANT_ATTRIBUTES);
            $variantAttributes = $this->json->unserialize($configValue ?? '[]') ?? [];
            $variantAttributes = $this->addMagentoAttributeData($variantAttributes);
            $this->variantAttributes = $variantAttributes;
        }

        return $this->variantAttributes;
    }

    /**
     * Get all configured attributes
     *
     * @return array
     */
    public function getAllAttributes(): array
    {
        if (!isset($this->allAttributes)) {
            $this->allAttributes = array_merge($this->getProductAttributes(), $this->getVariantAttributes());
        }
        return $this->allAttributes;
    }

    /**
     * Get all boolean attributes
     *
     * @return array
     */
    public function getBooleanAttributes(): array
    {
        if (!isset($this->booleanAttributes)) {
            $this->booleanAttributes = [];
            foreach ($this->getAllAttributes() as $attribute) {
                if (isset($attribute['frontend_input']) && $attribute['frontend_input'] === 'boolean') {
                    $this->booleanAttributes[] = $attribute;
                }
            }
        }
        return $this->booleanAttributes;
    }

    /**
     * Get attribute codes for all configured product attributes
     *
     * @param bool $includeCustom Include custom-defined attributes in array
     * @return string[]
     */
    public function getProductAttributeCodes(bool $includeCustom = false): array
    {
        if (!isset($this->productAttributeCodes)) {
            $attributeCodes = [];
            foreach ($this->getProductAttributes() as $attributeConfig) {
                $attributeCodes[] = $attributeConfig['attribute'];
            }
            $this->productAttributeCodes = $attributeCodes;
        }
        if ($includeCustom) {
            return array_merge(
                $this->productAttributeCodes,
                $this->customAttributeConfig->getProductLevelCustomAttributes()
            );
        }
        return $this->productAttributeCodes;
    }

    /**
     * Get attribute codes for all configured variant attributes
     *
     * @param bool $includeCustom Include custom-defined attributes in array
     * @return string[]
     */
    public function getVariantAttributeCodes(bool $includeCustom = false): array
    {
        if (!isset($this->variantAttributeCodes)) {
            $attributeCodes = [];
            foreach ($this->getVariantAttributes() as $attributeConfig) {
                $attributeCodes[] = $attributeConfig['attribute'];
            }
            $this->variantAttributeCodes = $attributeCodes;
        }
        if ($includeCustom) {
            return array_merge(
                $this->variantAttributeCodes,
                $this->customAttributeConfig->getVariantLevelCustomAttributes()
            );
        }
        return $this->variantAttributeCodes;
    }

    /**
     * Add Magento attribute data to array of attributes
     *
     * @param array $attributesConfig
     * @return array
     */
    private function addMagentoAttributeData(array $attributesConfig): array
    {
        $defaultStoreId = $this->generalConfig->getDefaultStore();
        $indexableAttributes = $this->attributeDataProvider->getIndexableAttributes();
        foreach ($attributesConfig as $key => $attributeConfig) {
            $attributeCode = $attributeConfig['attribute'];
            $indexableAttribute = $indexableAttributes[$attributeCode] ?? null;
            if ($indexableAttribute !== null) {
                $attributeConfig['backend_type'] = $indexableAttribute->getBackendType();
                $attributeConfig['attribute_id'] = $indexableAttribute->getAttributeId();
                $attributeConfig['frontend_input'] = $indexableAttribute->getFrontendInput();
                $attributeConfig['label'] = $indexableAttribute->getStoreLabel($defaultStoreId);
            }
            $attributesConfig[$key] = $attributeConfig;
        }
        return $attributesConfig;
    }

    /**
     * Get all static attributes
     *
     * @return array
     */
    public function getStaticAttributes(): array
    {
        if (!isset($this->staticAttributes)) {
            $staticAttributes = [];
            foreach ($this->getAllAttributes() as $productAttribute) {
                if ($productAttribute['backend_type'] === 'static') {
                    $staticAttributes[$productAttribute['attribute_id']] = $productAttribute['attribute'];
                }
            }
            $this->staticAttributes = $staticAttributes;
        }
        return $this->staticAttributes;
    }

    /**
     * Get all attributes along with their FH attribute type
     *
     * @return array
     */
    public function getAttributesWithFredhopperType(): array
    {
        if (!isset($this->attributesWithFredhopperType)) {
            $attributesWithFredhopperType = [];
            $allAttributes = $this->getAllAttributes();
            foreach ($allAttributes as $attribute) {
                $attributesWithFredhopperType[$attribute['attribute']] = $attribute['fredhopper_type'];
            }
            $this->attributesWithFredhopperType = $attributesWithFredhopperType;
        }
        return $this->attributesWithFredhopperType;
    }

    /**
     * Get all EAV attributes with grouped by backend type
     *
     * @return array
     */
    public function getEavAttributesByType(): array
    {
        if (!isset($this->eavAttributesByType)) {
            $eavAttributesByType = [];
            $allAttributes = $this->getAllAttributes();
            foreach ($allAttributes as $attribute) {
                $backendType = $attribute['backend_type'];
                if ($backendType === 'static') {
                    continue;
                }
                $eavAttributesByType[$backendType] = $eavAttributesByType[$backendType] ?? [];
                if (!in_array($attribute['attribute'], $eavAttributesByType[$backendType])) {
                    $eavAttributesByType[$backendType][] = $attribute['attribute_id'];
                }
            }
            $this->eavAttributesByType = $eavAttributesByType;
        }
        return $this->eavAttributesByType;
    }

    /**
     * Get all configured site variant attributes
     *
     * @return array
     */
    public function getSiteVariantAttributes(): array
    {
        if (!isset($this->siteVariantAttributes)) {
            $siteVariantAttributes = [];
            foreach ($this->getAllAttributes() as $attribute) {
                if ($attribute['append_site_variant']) {
                    $siteVariantAttributes[] = $attribute['attribute'];
                }
            }
            $this->siteVariantAttributes = array_unique($siteVariantAttributes);
        }
        return $this->siteVariantAttributes;
    }

    /**
     * Get mapping of attribute names between Magento and Fredhopper
     *
     * @return array
     */
    public function getAttributeNameMapping(): array
    {
        if (!isset($this->attributeNameMapping)) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_ATTRIBUTE_MAPPING);
            $this->attributeNameMapping = $this->json->unserialize($configValue ?? '[]') ?? [];
        }
        return $this->attributeNameMapping;
    }
}
