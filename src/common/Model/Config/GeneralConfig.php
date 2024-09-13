<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Model\Config;

use Magento\Catalog\Model\Category;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class GeneralConfig
{
    public const string ROOT_CATEGORY_NAME = 'catalog01';

    public const string XML_PATH_PREFIX = 'fredhopper_indexer/general/';
    public const string XML_PATH_USERNAME = self::XML_PATH_PREFIX . 'username';
    public const string XML_PATH_PASSWORD = self::XML_PATH_PREFIX . 'password';
    public const string XML_PATH_ENVIRONMENT = self::XML_PATH_PREFIX . 'environment_name';
    public const string XML_PATH_ENDPOINT = self::XML_PATH_PREFIX . 'endpoint_name';
    public const string XML_PATH_PRODUCT_PREFIX = self::XML_PATH_PREFIX . 'product_prefix';
    public const string XML_PATH_VARIANT_PREFIX = self::XML_PATH_PREFIX . 'variant_prefix';
    public const string XML_PATH_USE_SITE_VARIANT = self::XML_PATH_PREFIX . 'use_site_variant';
    public const string XML_PATH_DEFAULT_STORE = self::XML_PATH_PREFIX . 'default_store';
    public const string XML_PATH_EXCLUDED_STORES = self::XML_PATH_PREFIX . 'excluded_stores';
    public const string XML_PATH_SITE_VARIANT = self::XML_PATH_PREFIX . 'site_variant';
    public const string XML_PATH_ROOT_CATEGORY = self::XML_PATH_PREFIX . 'root_category';
    public const string XML_PATH_DEBUG_LOGGING = self::XML_PATH_PREFIX . 'debug_logging';

    /** @var string[] */
    private array $allSiteVariantSuffixes;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Resolver $localeResolver
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Resolver $localeResolver,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_USERNAME);
    }

    /**
     * Get password
     *
     * @return string
     */
    public function getPassword(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_PASSWORD);
    }

    /**
     * Get environment name
     *
     * @return string
     */
    public function getEnvironmentName(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_ENVIRONMENT);
    }

    /**
     * Get endpoint name
     *
     * @return string
     */
    public function getEndpointName(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_ENDPOINT);
    }

    /**
     * Get product prefix
     *
     * @return string
     */
    public function getProductPrefix(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_PRODUCT_PREFIX);
    }

    /**
     * Get variant prefix
     *
     * @return string
     */
    public function getVariantPrefix(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_VARIANT_PREFIX);
    }

    /**
     * Get site variant flag
     *
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
    }

    /**
     * Get default store
     *
     * @return int
     */
    public function getDefaultStore(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_DEFAULT_STORE);
    }

    /**
     * Get all excluded stores
     *
     * @return array
     */
    public function getExcludedStores(): array
    {
        $configValue = (string)$this->scopeConfig->getValue((self::XML_PATH_EXCLUDED_STORES));
        return explode(',', $configValue);
    }

    /**
     * Get default locale
     *
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
     * Get the site variant for a store
     *
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
     * Get all site variant suffixes
     *
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
     * Get root category ID
     *
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
     * Get debug logging setting
     *
     * @return bool
     */
    public function getDebugLogging(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG_LOGGING);
    }
}
