<?php
namespace Aligent\FredhopperIndexer\Helper;

class AgeAttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/age_config/';
    public const XML_PATH_SEND_NEW_INDICATOR = self::XML_PATH_PREFIX . 'send_new_indicator';
    public const XML_PATH_SEND_DAYS_ONLINE = self::XML_PATH_PREFIX . 'send_days_online';
    public const XML_PATH_CREATED_AT_FIELD = self::XML_PATH_PREFIX . 'created_at_field';
    public const XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';

    /** @var bool */
    protected $sendNewIndicator;
    /** @var bool */
    protected $sendDaysOnline;
    /** @var string */
    protected $createdAtFieldName;
    /** @var bool */
    protected $useSiteVariantAge;

    public function getSendNewIndicator()
    {
        if ($this->sendNewIndicator === null) {
            $this->sendNewIndicator = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_NEW_INDICATOR);
        }
        return $this->sendNewIndicator;
    }

    public function getSendDaysOnline()
    {
        if ($this->sendDaysOnline === null) {
            $this->sendDaysOnline = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_DAYS_ONLINE);
        }
        return $this->sendDaysOnline;
    }

    public function getCreatedAtFieldName()
    {
        if ($this->createdAtFieldName === null) {
            $this->createdAtFieldName = $this->scopeConfig->getValue(self::XML_PATH_CREATED_AT_FIELD);
        }
        return $this->createdAtFieldName;
    }

    public function getUseSiteVariant()
    {
        if ($this->useSiteVariantAge === null) {
            $this->useSiteVariantAge = parent::getUseSiteVariant() &&
                $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
        }
        return $this->useSiteVariantAge;
    }

    public function getAllSiteVariantSuffixes()
    {
        return $this->getUseSiteVariant() ? parent::getAllSiteVariantSuffixes() : [''];
    }
}
