<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Model\Config;

class CustomAttributeConfig
{
    private const string ATTRIBUTE_LEVEL_PRODUCT = 'product';
    private const string ATTRIBUTE_LEVEL_VARIANT = 'variant';

    /** @var array */
    private array $siteVariantCustomAttributes;
    /** @var array */
    private array $productLevelCustomAttributes;
    /** @var array */
    private array $variantLevelCustomAttributes;

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
        if (!isset($this->siteVariantCustomAttributes)) {
            $this->siteVariantCustomAttributes = [];
            foreach ($this->customAttributeData as $attributeCode => $attributeData) {
                if ($attributeData['is_site_variant'] ?? false) {
                    $this->siteVariantCustomAttributes[] = $attributeCode;
                }
            }
        }
        return $this->siteVariantCustomAttributes;
    }

    /**
     * Get product-level custom attributes
     *
     * @return array
     */
    public function getProductLevelCustomAttributes(): array
    {
        if (!isset($this->productLevelCustomAttributes)) {
            $this->productLevelCustomAttributes = [];
            foreach ($this->customAttributeData as $attributeCode => $attributeData) {
                $attributeLevel = $attributeData['level'] ?? self::ATTRIBUTE_LEVEL_PRODUCT;
                if ($attributeLevel === self::ATTRIBUTE_LEVEL_PRODUCT) {
                    $this->productLevelCustomAttributes[] = $attributeCode;
                }
            }
        }
        return $this->productLevelCustomAttributes;
    }

    /**
     * Get variant-level custom attributes
     *
     * @return array
     */
    public function getVariantLevelCustomAttributes(): array
    {
        if (!isset($this->variantLevelCustomAttributes)) {
            $this->variantLevelCustomAttributes = [];
            foreach ($this->customAttributeData as $attributeCode => $attributeData) {
                $attributeLevel = $attributeData['level'] ?? self::ATTRIBUTE_LEVEL_PRODUCT;
                if ($attributeLevel === self::ATTRIBUTE_LEVEL_VARIANT) {
                    $this->variantLevelCustomAttributes[] = $attributeCode;
                }
            }
        }
        return $this->variantLevelCustomAttributes;
    }
}
