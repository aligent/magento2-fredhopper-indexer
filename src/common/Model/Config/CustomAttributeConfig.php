<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Model\Config;

class CustomAttributeConfig
{
    private const ATTRIBUTE_LEVEL_PRODUCT = 'product';
    private const ATTRIBUTE_LEVEL_VARIANT = 'variant';

    /**
     * @param array $customAttributeData
     */
    public function __construct(
        private readonly array $customAttributeData = []
    ) {
    }

    public function getCustomAttributeData(): array
    {
        return $this->customAttributeData;
    }

    /**
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
