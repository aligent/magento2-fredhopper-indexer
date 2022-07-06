<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

use Magento\Catalog\Model\Category;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class GeneralConfig extends AbstractHelper
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
    public const XML_PATH_DEBUG_LOGGING = self::XML_PATH_PREFIX . 'debug_logging';

    private Resolver $localeResolver;
    private StoreManagerInterface $storeManager;

    private string $username;
    private string $password;
    private string $environmentName;
    private string $endpointName;
    private string $productPrefix;
    private string $variantPrefix;
    private bool $useSiteVariant;
    private int $defaultStore;
    private array $excludedStores;
    private string $defaultLocale;
    private int $rootCategoryId;
    private bool $debugLogging;

    /** @var string[] */
    private array $siteVariants = [];
    /** @var string[] */
    private array $allSiteVariantSuffixes;

    public function __construct(
        Context $context,
        Resolver $localeResolver,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->localeResolver = $localeResolver;
        $this->storeManager = $storeManager;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        if (!isset($this->username)) {
            $this->username = (string)$this->scopeConfig->getValue(self::XML_PATH_USERNAME);
        }
        return $this->username;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        if (!isset($this->password)) {
            $this->password = (string)$this->scopeConfig->getValue(self::XML_PATH_PASSWORD);
        }
        return $this->password;
    }

    /**
     * @return string
     */
    public function getEnvironmentName(): string
    {
        if (!isset($this->environmentName)) {
            $this->environmentName = (string)$this->scopeConfig->getValue(self::XML_PATH_ENVIRONMENT);
        }
        return $this->environmentName;
    }

    /**
     * @return string
     */
    public function getEndpointName(): string
    {
        if (!isset($this->endpointName)) {
            $this->endpointName = (string)$this->scopeConfig->getValue(self::XML_PATH_ENDPOINT);
        }
        return $this->endpointName;
    }

    /**
     * @return string
     */
    public function getProductPrefix(): string
    {
        if (!isset($this->productPrefix)) {
            $this->productPrefix = (string)$this->scopeConfig->getValue(self::XML_PATH_PRODUCT_PREFIX);
        }
        return $this->productPrefix;
    }

    /**
     * @return string
     */
    public function getVariantPrefix(): string
    {
        if (!isset($this->variantPrefix)) {
            $this->variantPrefix = (string)$this->scopeConfig->getValue(self::XML_PATH_VARIANT_PREFIX);
        }
        return $this->variantPrefix;
    }

    /**
     * @return bool
     */
    public function getUseSiteVariant(): bool
    {
        if (!isset($this->useSiteVariant)) {
            $this->useSiteVariant = $this->scopeConfig->isSetFlag(self::XML_PATH_USE_SITE_VARIANT);
        }
        return $this->useSiteVariant;
    }

    /**
     * @return int
     */
    public function getDefaultStore(): int
    {
        if (!isset($this->defaultStore)) {
            $this->defaultStore = (int)$this->scopeConfig->getValue(self::XML_PATH_DEFAULT_STORE);
        }
        return $this->defaultStore;
    }

    /**
     * @return array
     */
    public function getExcludedStores(): array
    {
        if (!isset($this->excludedStores)) {
            $configValue = (string)$this->scopeConfig->getValue((self::XML_PATH_EXCLUDED_STORES));
            $this->excludedStores = explode(',', $configValue);
        }
        return $this->excludedStores;
    }

    /**
     * @return string
     */
    public function getDefaultLocale(): string
    {
        if (!isset($this->defaultLocale)) {
            $this->localeResolver->emulate($this->getDefaultStore());
            $this->defaultLocale = $this->localeResolver->getLocale();
            $this->localeResolver->revert();
        }
        return $this->defaultLocale;
    }

    /**
     * @param int|null $storeId
     * @return string|null
     */
    public function getSiteVariant(?int $storeId = null): ?string
    {
        $storeKey = $storeId ?? 'default';
        if (!isset($this->siteVariants[$storeKey])) {
            $this->siteVariants[$storeKey] = $this->scopeConfig->getValue(
                self::XML_PATH_SITE_VARIANT,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }
        return $this->siteVariants[$storeKey];
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
                    if (in_array($store->getId(), $this->getExcludedStores())) {
                        continue;
                    }
                    $this->allSiteVariantSuffixes[] = '_' . $this->getSiteVariant((int)$store->getId());
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
        if (!isset($this->rootCategoryId)) {
            $this->rootCategoryId = (int) $this->scopeConfig->getValue(self::XML_PATH_ROOT_CATEGORY);
            if ($this->rootCategoryId <= 0) {
                $this->rootCategoryId = Category::TREE_ROOT_ID;
            }
        }
        return $this->rootCategoryId;
    }

    /**
     * @return bool
     */
    public function getDebugLogging(): bool
    {
        if (!isset($this->debugLogging)) {
            $this->debugLogging = $this->scopeConfig->isSetFlag(self::XML_PATH_DEBUG_LOGGING);
        }
        return $this->debugLogging;
    }
}
