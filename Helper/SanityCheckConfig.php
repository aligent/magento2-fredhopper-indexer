<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

class SanityCheckConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/sanity_check/';
    public const XML_PATH_MIN_TOTAL = self::XML_PATH_PREFIX . 'total_products';
    public const XML_PATH_MIN_CATEGORY_TIER1 = self::XML_PATH_PREFIX . 'cat_tier1';
    public const XML_PATH_MIN_CATEGORY_TIER2 = self::XML_PATH_PREFIX . 'cat_tier2';

    public function getMinTotalProducts(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_TOTAL);
    }

    public function getMinProductsCategoryTier1(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_CATEGORY_TIER1);
    }

    public function getMinProductsCategoryTier2(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_CATEGORY_TIER2);
    }
}
