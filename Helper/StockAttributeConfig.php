<?php
namespace Aligent\FredhopperIndexer\Helper;

class StockAttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/stock_config/';
    public const XML_PATH_SEND_STOCK_STATUS = self::XML_PATH_PREFIX . 'send_stock_status';
    public const XML_PATH_SEND_STOCK_COUNT = self::XML_PATH_PREFIX . 'send_stock_count';
    public const XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';

    /** @var bool */
    protected $sendStockStatus;
    /** @var bool */
    protected $sendStockCount;
    /** @var bool */
    protected $useSiteVariantStock;

    /**
     * @return bool
     */
    public function getSendStockStatus(): bool
    {
        if ($this->sendStockStatus === null) {
            $this->sendStockStatus = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_STOCK_STATUS);
        }
        return $this->sendStockStatus;
    }

    /**
     * @return bool
     */
    public function getSendStockCount(): bool
    {
        if ($this->sendStockCount === null) {
            $this->sendStockCount = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_STOCK_COUNT);
        }
        return $this->sendStockCount;
    }

    /**
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        if ($this->useSiteVariantStock === null) {
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
