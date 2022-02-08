<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

class SanityCheckConfig extends GeneralConfig
{
    public const EMAIL_TEMPLATE = 'fh_indexer_sanity_check_email_template';
    public const XML_PATH_PREFIX = 'fredhopper_indexer/sanity_check/';
    public const XML_PATH_MIN_TOTAL = self::XML_PATH_PREFIX . 'total_products';
    public const XML_PATH_MIN_CATEGORY_TIER1 = self::XML_PATH_PREFIX . 'cat_tier1';
    public const XML_PATH_MIN_CATEGORY_TIER2 = self::XML_PATH_PREFIX . 'cat_tier2';
    public const XML_PATH_REPORT_EMAIL = self::XML_PATH_PREFIX . 'report_email';

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

    public function getErrorEmailRecipients(): array
    {
        $rawConfig = $this->scopeConfig->getValue(self::XML_PATH_REPORT_EMAIL);
        $emails = array_filter(preg_split('/,\s*/', $rawConfig));
        return $emails;
    }
}