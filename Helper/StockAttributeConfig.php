<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

class StockAttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/stock_config/';
    public const XML_PATH_SEND_STOCK_STATUS = self::XML_PATH_PREFIX . 'send_stock_status';
    public const XML_PATH_SEND_STOCK_COUNT = self::XML_PATH_PREFIX . 'send_stock_count';
    public const XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';

    private bool $sendStockStatus;
    private bool $sendStockCount;
    private bool $useSiteVariantStock;

    /**
     * @return bool
     */
    public function getSendStockStatus(): bool
    {
        if (!isset($this->sendStockStatus)) {
            $this->sendStockStatus = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_STOCK_STATUS);
        }
        return $this->sendStockStatus;
    }

    /**
     * @return bool
     */
    public function getSendStockCount(): bool
    {
        if (!isset($this->sendStockCount)) {
            $this->sendStockCount = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_STOCK_COUNT);
        }
        return $this->sendStockCount;
    }

    /**
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        if (!isset($this->useSiteVariantStock)) {
            $this->useSiteVariantStock = parent::getUseSiteVariant() &&
                $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
        }
        return $this->useSiteVariantStock;
    }

    /**
     * @return string[]
     */
    public function getAllSiteVariantSuffixes(): array
    {
        return $this->getUseSiteVariant() ? parent::getAllSiteVariantSuffixes() : [''];
    }
}
