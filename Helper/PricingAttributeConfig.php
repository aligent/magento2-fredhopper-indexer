<?php
namespace Aligent\FredhopperIndexer\Helper;

class PricingAttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/pricing_config/';
    public const XML_PATH_USE_CUSTOMER_GROUP = self::XML_PATH_PREFIX . 'use_customer_group';
    public const XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';
    public const XML_PATH_USE_RANGE = self::XML_PATH_PREFIX . 'use_range';

    /** @var bool */
    protected $useCustomerGroup;
    /** @var bool */
    protected $useSiteVariantPricing;
    /** @var bool */
    protected $useRange;

    /**
     * @return bool
     */
    public function getUseCustomerGroup(): bool
    {
        if ($this->useCustomerGroup === null) {
            $this->useCustomerGroup = $this->scopeConfig->isSetFlag(self::XML_PATH_USE_CUSTOMER_GROUP);
        }
        return $this->useCustomerGroup;
    }

    /**
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        if ($this->useSiteVariantPricing === null) {
            $this->useSiteVariantPricing = parent::getUseSiteVariant() &&
                $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
        }
        return $this->useSiteVariantPricing;
    }

    /**
     * @return bool
     */
    public function getUseRange(): bool
    {
        if ($this->useRange === null) {
            $this->useRange = $this->scopeConfig->isSetFlag(self::XML_PATH_USE_RANGE);
        }
        return $this->useRange;
    }

    /**
     * @return string[]
     */
    public function getAllSiteVariantSuffixes(): array
    {
        return $this->getUseSiteVariant() ? parent::getAllSiteVariantSuffixes() : [''];
    }
}
