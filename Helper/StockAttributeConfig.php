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

    public function getSendStockStatus()
    {
        if ($this->sendStockStatus === null) {
            $this->sendStockStatus = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_STOCK_STATUS);
        }
        return $this->sendStockStatus;
    }

    public function getSendStockCount()
    {
        if ($this->sendStockCount === null) {
            $this->sendStockCount = $this->scopeConfig->isSetFlag(self::XML_PATH_SEND_STOCK_COUNT);
        }
        return $this->sendStockCount;
    }

    public function getUseSiteVariant()
    {
        if ($this->useSiteVariantStock === null) {
            $this->useSiteVariantStock = parent::getUseSiteVariant() &&
                $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
        }
        return $this->useSiteVariantStock;
    }

    public function getAllSiteVariantSuffixes()
    {
        return $this->getUseSiteVariant() ? parent::getAllSiteVariantSuffixes() : [''];
    }
}
