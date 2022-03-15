<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

class SanityCheckConfig extends GeneralConfig
{
    public const EMAIL_TEMPLATE = 'fh_indexer_sanity_check_email_template';
    public const XML_PATH_PREFIX = 'fredhopper_indexer/sanity_check/';
    public const XML_PATH_MIN_TOTAL = self::XML_PATH_PREFIX . 'total_products';
    public const XML_PATH_MAX_DELETE = self::XML_PATH_PREFIX . 'delete_products';
    public const XML_PATH_MIN_CATEGORY_TIER1 = self::XML_PATH_PREFIX . 'cat_tier1';
    public const XML_PATH_MIN_CATEGORY_TIER2 = self::XML_PATH_PREFIX . 'cat_tier2';
    public const XML_PATH_REPORT_EMAIL = self::XML_PATH_PREFIX . 'report_email';

    /**
     * @return int
     */
    public function getMinTotalProducts(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_TOTAL);
    }

    /**
     * @return int
     */
    public function getMaxDeleteProducts(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_DELETE);
    }

    /**
     * @return int
     */
    public function getMinProductsCategoryTier1(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_CATEGORY_TIER1);
    }

    /**
     * @return int
     */
    public function getMinProductsCategoryTier2(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_CATEGORY_TIER2);
    }

    /**
     * @return array
     */
    public function getErrorEmailRecipients(): array
    {
        $rawConfig = $this->scopeConfig->getValue(self::XML_PATH_REPORT_EMAIL);
        return array_filter(preg_split('/,\s*/', $rawConfig));
    }
}
