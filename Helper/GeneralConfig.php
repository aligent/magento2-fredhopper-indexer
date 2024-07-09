<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

use Magento\Catalog\Model\Category;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/general/';
    public const XML_PATH_USERNAME = self::XML_PATH_PREFIX . 'username';
    public const XML_PATH_PASSWORD = self::XML_PATH_PREFIX . 'password';
    public const XML_PATH_ENVIRONMENT = self::XML_PATH_PREFIX . 'environment_name';
    public const XML_PATH_ENDPOINT = self::XML_PATH_PREFIX . 'endpoint_name';
    public const XML_PATH_PRODUCT_PREFIX = self::XML_PATH_PREFIX . 'product_prefix';
    public const XML_PATH_VARIANT_PREFIX = self::XML_PATH_PREFIX . 'variant_prefix';
    public const XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';
    public const XML_PATH_DEFAULT_STORE = self::XML_PATH_PREFIX . 'default_store';
    public const XML_PATH_EXCLUDED_STORES = self::XML_PATH_PREFIX . 'excluded_stores';
    public const XML_PATH_SITE_VARIANT = self::XML_PATH_PREFIX . 'site_variant';
    public const XML_PATH_ROOT_CATEGORY = self::XML_PATH_PREFIX . 'root_category';
    public const XML_PATH_EXPORT_DIRECTORY = self::XML_PATH_PREFIX . 'export_directory';
    public const XML_PATH_DEBUG_LOGGING = self::XML_PATH_PREFIX . 'debug_logging';

    /** @var string[] */
    private array $allSiteVariantSuffixes;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Resolver $localeResolver,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_USERNAME);
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_PASSWORD);
    }

    /**
     * @return string
     */
    public function getEnvironmentName(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_ENVIRONMENT);
    }

    /**
     * @return string
     */
    public function getEndpointName(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_ENDPOINT);
    }

    /**
     * @return string
     */
    public function getProductPrefix(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_PRODUCT_PREFIX);
    }

    /**
     * @return string
     */
    public function getVariantPrefix(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_VARIANT_PREFIX);
    }

    /**
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
    }

    /**
     * @return int
     */
    public function getDefaultStore(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_DEFAULT_STORE);
    }

    /**
     * @return array
     */
    public function getExcludedStores(): array
    {
        $configValue = (string)$this->scopeConfig->getValue((self::XML_PATH_EXCLUDED_STORES));
        return explode(',', $configValue);
    }

    /**
     * @return string
     */
    public function getDefaultLocale(): string
    {
        $this->localeResolver->emulate($this->getDefaultStore());
        $defaultLocale = $this->localeResolver->getLocale();
        $this->localeResolver->revert();
        return $defaultLocale;
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getSiteVariant(?int $storeId = null): ?string
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SITE_VARIANT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * @return string[]
     */
    public function getAllSiteVariantSuffixes(): array
    {
        if (!isset($this->allSiteVariantSuffixes)) {
            if (!$this->getUseSiteVariant()) {
                $this->allSiteVariantSuffixes = ['']; // single empty string, rather than empty array
            } else {
                foreach ($this->storeManager->getStores() as $store) {
                    $storeId = (int)$store->getId();
                    if (in_array($storeId, $this->getExcludedStores())) {
                        continue;
                    }
                    $siteVariant = $this->getSiteVariant($storeId);
                    $this->allSiteVariantSuffixes[$siteVariant] = '_' . $siteVariant;
                }
            }
        }
        return $this->allSiteVariantSuffixes;
    }

    /**
     * @return int
     */
    public function getRootCategoryId(): int
    {
        $rootCategoryId = (int) $this->scopeConfig->getValue(self::XML_PATH_ROOT_CATEGORY);
        if ($rootCategoryId <= 0) {
            return Category::TREE_ROOT_ID;
        }
        return $rootCategoryId;
    }

    /**
     * Get configured export directory path
     *
     * @return string
     */
    public function getExportDirectory(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_EXPORT_DIRECTORY);
    }

    /**
     * @return bool
     */
    public function getDebugLogging(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG_LOGGING);
    }
}
