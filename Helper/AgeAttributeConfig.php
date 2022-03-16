<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

class AgeAttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/age_config/';
    public const XML_PATH_SEND_NEW_INDICATOR = self::XML_PATH_PREFIX . 'send_new_indicator';
    public const XML_PATH_SEND_DAYS_ONLINE = self::XML_PATH_PREFIX . 'send_days_online';
    public const XML_PATH_CREATED_AT_FIELD = self::XML_PATH_PREFIX . 'created_at_field';
    public const XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';

    private bool $sendNewIndicator;
    private bool $sendDaysOnline;
    private string $createdAtFieldName;
    private bool $useSiteVariantAge;

    /**
     * @return bool
     */
    public function getSendNewIndicator(): bool
    {
        if (!isset($this->sendNewIndicator)) {
            $this->sendNewIndicator = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_NEW_INDICATOR);
        }
        return $this->sendNewIndicator;
    }

    /**
     * @return bool
     */
    public function getSendDaysOnline(): bool
    {
        if (!isset($this->sendDaysOnline)) {
            $this->sendDaysOnline = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_DAYS_ONLINE);
        }
        return $this->sendDaysOnline;
    }

    /**
     * @return string
     */
    public function getCreatedAtFieldName(): string
    {
        if (!isset($this->createdAtFieldName)) {
            $this->createdAtFieldName = (string)$this->scopeConfig->getValue(self::XML_PATH_CREATED_AT_FIELD);
        }
        return $this->createdAtFieldName;
    }

    /**
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        if (!isset($this->useSiteVariantAge)) {
            $this->useSiteVariantAge = parent::getUseSiteVariant() &&
                $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
        }
        return $this->useSiteVariantAge;
    }

    /**
     * @return string[]
     */
    public function getAllSiteVariantSuffixes(): array
    {
        return $this->getUseSiteVariant() ? parent::getAllSiteVariantSuffixes() : [''];
    }
}
