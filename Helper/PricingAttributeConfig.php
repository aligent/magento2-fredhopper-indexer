<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

class PricingAttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/pricing_config/';
    public const XML_PATH_USE_CUSTOMER_GROUP = self::XML_PATH_PREFIX . 'use_customer_group';
    public const XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';
    public const XML_PATH_USE_RANGE = self::XML_PATH_PREFIX . 'use_range';

    private bool $useCustomerGroup;
    private bool $useSiteVariantPricing;
    private bool $useRange;

    /**
     * @return bool
     */
    public function getUseCustomerGroup(): bool
    {
        if (!isset($this->useCustomerGroup)) {
            $this->useCustomerGroup = $this->scopeConfig->isSetFlag(self::XML_PATH_USE_CUSTOMER_GROUP);
        }
        return $this->useCustomerGroup;
    }

    /**
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        if (!isset($this->useSiteVariantPricing)) {
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
        if (!isset($this->useRange)) {
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
