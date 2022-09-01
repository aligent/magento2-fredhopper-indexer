<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\StoreManagerInterface;

class CustomAttributeConfig extends GeneralConfig
{
    private const ATTRIBUTE_LEVEL_PRODUCT = 'product';
    private const ATTRIBUTE_LEVEL_VARIANT = 'variant';

    /** @var array */
    private array $customAttributeData;
    /** string[] */
    private array $siteVariantCustomAttributes;
    private array $productLevelCustomAttributeCodes;
    private array $variantLevelCustomAttributeCodes;

    public function __construct(
        Context $context,
        Resolver $localeResolver,
        StoreManagerInterface $storeManager,
        array $customAttributeData = []
    ) {
        parent::__construct($context, $localeResolver, $storeManager);

        $this->customAttributeData = $customAttributeData;
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
     * @return array
     */
    public function getProductLevelCustomAttributes(): array
    {
        if (!isset($this->productLevelCustomAttributeCodes)) {
            $this->productLevelCustomAttributeCodes = [];
            foreach ($this->customAttributeData as $attributeCode => $attributeData) {
                $attributeLevel = $attributeData['level'] ?? self::ATTRIBUTE_LEVEL_PRODUCT;
                if ($attributeLevel === self::ATTRIBUTE_LEVEL_PRODUCT) {
                    $this->productLevelCustomAttributeCodes[] = $attributeCode;
                }
            }
        }
        return $this->productLevelCustomAttributeCodes;
    }

    /**
     * @return array
     */
    public function getVariantLevelCustomAttributes(): array
    {
        if (!isset($this->variantLevelCustomAttributeCodes)) {
            $this->variantLevelCustomAttributeCodes = [];
            foreach ($this->customAttributeData as $attributeCode => $attributeData) {
                $attributeLevel = $attributeData['level'] ?? self::ATTRIBUTE_LEVEL_PRODUCT;
                if ($attributeLevel === self::ATTRIBUTE_LEVEL_VARIANT) {
                    $this->variantLevelCustomAttributeCodes[] = $attributeCode;
                }
            }
        }
        return $this->variantLevelCustomAttributeCodes;
    }
}
