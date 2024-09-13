<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Model\Config;

class CustomAttributeConfig
{
    private const string ATTRIBUTE_LEVEL_PRODUCT = 'product';
    private const string ATTRIBUTE_LEVEL_VARIANT = 'variant';

    /**
     * @param array $customAttributeData
     */
    public function __construct(
        private readonly array $customAttributeData = []
    ) {
    }

    /**
     * Get custom attribute data
     *
     * @return array
     */
    public function getCustomAttributeData(): array
    {
        return $this->customAttributeData;
    }

    /**
     * Get all site-variant custom attributes
     *
     * @return array
     */
    public function getSiteVariantCustomAttributes(): array
    {
        $siteVariantCustomAttributes = [];
        foreach ($this->customAttributeData as $attributeCode => $attributeData) {
            if ($attributeData['is_site_variant'] ?? false) {
                $siteVariantCustomAttributes[] = $attributeCode;
            }
        }
        return $siteVariantCustomAttributes;
    }

    /**
     * Get product-level custom attributes
     *
     * @return array
     */
    public function getProductLevelCustomAttributes(): array
    {
        $productLevelCustomAttributeCodes = [];
        foreach ($this->customAttributeData as $attributeCode => $attributeData) {
            $attributeLevel = $attributeData['level'] ?? self::ATTRIBUTE_LEVEL_PRODUCT;
            if ($attributeLevel === self::ATTRIBUTE_LEVEL_PRODUCT) {
                $productLevelCustomAttributeCodes[] = $attributeCode;
            }
        }
        return $productLevelCustomAttributeCodes;
    }

    /**
     * Get variant-level custom attributes
     *
     * @return array
     */
    public function getVariantLevelCustomAttributes(): array
    {
        $variantLevelCustomAttributeCodes = [];
        foreach ($this->customAttributeData as $attributeCode => $attributeData) {
            $attributeLevel = $attributeData['level'] ?? self::ATTRIBUTE_LEVEL_PRODUCT;
            if ($attributeLevel === self::ATTRIBUTE_LEVEL_VARIANT) {
                $variantLevelCustomAttributeCodes[] = $attributeCode;
            }
        }
        return $variantLevelCustomAttributeCodes;
    }
}
