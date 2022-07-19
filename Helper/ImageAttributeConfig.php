<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

class ImageAttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/image_config/';
    public const XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';

    private bool $useSiteVariantImages;

    /**
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        if (!isset($this->useSiteVariantImages)) {
            $this->useSiteVariantImages = parent::getUseSiteVariant() &&
                $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
        }
        return $this->useSiteVariantImages;
    }

    /**
     * @return string[]
     */
    public function getAllSiteVariantSuffixes(): array
    {
        return $this->getUseSiteVariant() ? parent::getAllSiteVariantSuffixes() : [''];
    }
}
