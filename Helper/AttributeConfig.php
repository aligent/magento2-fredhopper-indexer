<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;

class AttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/product_config/';
    public const XML_PATH_USE_VARIANT_PRODUCTS = self::XML_PATH_PREFIX . 'use_variant_products';
    public const XML_PATH_PRODUCT_ATTRIBUTES = self::XML_PATH_PREFIX . 'product_attributes';
    public const XML_PATH_VARIANT_ATTRIBUTES = self::XML_PATH_PREFIX . 'variant_attributes';

    /**
     * @var Json
     */
    protected $json;
    /**
     * @var DataProvider
     */
    protected $dataProvider;

    /** @var bool */
    protected $useVariantProducts;
    /** @var array */
    protected $productAttributes;
    /** @var array */
    protected $variantAttributes;
    /** @var array */
    protected $allAttributes;
    /** @var array */
    protected $booleanAttributes;
    /** @var string[] */
    protected $productAttributeCodes;
    /** @var string[] */
    protected $variantAttributeCodes;
    /** @var array */
    protected $searchableAttributes;
    /** @var array */
    protected $staticAttributes;
    /** @var array */
    protected $attributesWithFredhopperType;
    /** @var array */
    protected $eavAttributesByType;
    /** @var array */
    protected $siteVariantAttributes;

    public function __construct(
        Context $context,
        Resolver $localeResolver,
        StoreManagerInterface $storeManager,
        Json $json,
        DataProvider $dataProvider
    ) {
        parent::__construct($context, $localeResolver, $storeManager);
        $this->json = $json;
        $this->dataProvider = $dataProvider;
    }

    /**
     * @return bool
     */
    public function getUseVariantProducts(): bool
    {
        if ($this->useVariantProducts === null) {
            $this->useVariantProducts = $this->scopeConfig->isSetFlag(self::XML_PATH_USE_VARIANT_PRODUCTS);
        }
        return $this->useVariantProducts;
    }

    /**
     * @return array
     */
    public function getProductAttributes(): array
    {
        if ($this->productAttributes === null) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_PRODUCT_ATTRIBUTES);
            $productAttributes = $this->json->unserialize($configValue ?? '[]') ?? [];
            $this->addMagentoAttributeData($productAttributes);
            $this->productAttributes = $productAttributes;
        }
        return $this->productAttributes;
    }

    /**
     * @return array
     */
    public function getVariantAttributes(): array
    {
        if ($this->variantAttributes === null) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_VARIANT_ATTRIBUTES);
            $variantAttributes = $this->json->unserialize($configValue ?? '[]') ?? [];
            $this->addMagentoAttributeData($variantAttributes);
            $this->variantAttributes = $variantAttributes;
        }

        return $this->variantAttributes;
    }

    /**
     * @return array
     */
    public function getAllAttributes(): array
    {
        if ($this->allAttributes === null) {
            $this->allAttributes = array_merge($this->getProductAttributes(), $this->getVariantAttributes());
        }
        return $this->allAttributes;
    }

    /**
     * @return array
     */
    public function getBooleanAttributes(): array
    {
        if ($this->booleanAttributes === null) {
            $this->booleanAttributes = array_filter($this->getAllAttributes(), function ($attribute) {
                return isset($attribute['frontend_input']) && $attribute['frontend_input'] === 'boolean';
            });
        }
        return $this->booleanAttributes;
    }

    /**
     * @return string[]
     */
    public function getProductAttributeCodes(): array
    {
        if ($this->productAttributeCodes === null) {
            $attributeCodes = [];
            foreach ($this->getProductAttributes() as $attributeConfig) {
                $attributeCodes[] = $attributeConfig['attribute'];
            }
            $this->productAttributeCodes = $attributeCodes;
        }
        return $this->productAttributeCodes;
    }

    /**
     * @return string[]
     */
    public function getVariantAttributeCodes(): array
    {
        if ($this->variantAttributeCodes === null) {
            $attributeCodes = [];
            foreach ($this->getVariantAttributes() as $attributeConfig) {
                $attributeCodes[] = $attributeConfig['attribute'];
            }
            $this->variantAttributeCodes = $attributeCodes;
        }
        return $this->variantAttributeCodes;
    }

    /**
     * @return array
     */
    protected function getSearchableAttributes(): array
    {
        if ($this->searchableAttributes === null) {
            $this->searchableAttributes = $this->dataProvider->getSearchableAttributes();
        }
        return $this->searchableAttributes;
    }

    /**
     * @param array $attributesConfig
     */
    protected function addMagentoAttributeData(array &$attributesConfig): void
    {
        $defaultStoreId = $this->getDefaultStore();
        $searchableAttributes = $this->getSearchableAttributes();
        foreach ($attributesConfig as &$attributeConfig) {
            $attributeCode = $attributeConfig['attribute'];
            foreach ($searchableAttributes as $searchableAttributeCode => $attribute) {
                if ($attributeCode === $searchableAttributeCode) {
                    $attributeConfig['backend_type'] = $attribute->getBackendType();
                    $attributeConfig['attribute_id'] = $attribute->getAttributeId();
                    $attributeConfig['frontend_input'] = $attribute->getFrontendInput();
                    $attributeConfig['label'] = $attribute->getStoreLabel($defaultStoreId);
                    break;
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getStaticAttributes(): array
    {
        if ($this->staticAttributes === null) {
            $staticAttributes = [];
            foreach ($this->getAllAttributes() as $productAttribute) {
                if ($productAttribute['backend_type'] === 'static') {
                    $staticAttributes[$productAttribute['attribute_id']] = $productAttribute['attribute'];
                }
            }
            $this->staticAttributes = $staticAttributes;
        }
        return $this->staticAttributes;
    }

    /**
     * @return array
     */
    public function getAttributesWithFredhopperType(): array
    {
        if ($this->attributesWithFredhopperType === null) {
            $attributesWithFredhopperType = [];
            $allAttributes = $this->getAllAttributes();
            foreach ($allAttributes as $attribute) {
                $attributesWithFredhopperType[$attribute['attribute']] = $attribute['fredhopper_type'];
            }
            $this->attributesWithFredhopperType = $attributesWithFredhopperType;
        }
        return $this->attributesWithFredhopperType;
    }

    /**
     * @return array
     */
    public function getEavAttributesByType(): array
    {
        if ($this->eavAttributesByType === null) {
            $eavAttributesByType = [];
            $allAttributes = $this->getAllAttributes();
            foreach ($allAttributes as $attribute) {
                $backendType = $attribute['backend_type'];
                if ($backendType === 'static') {
                    continue;
                }
                $eavAttributesByType[$backendType] = $eavAttributesByType[$backendType] ?? [];
                if (!in_array($attribute['attribute'], $eavAttributesByType[$backendType])) {
                    $eavAttributesByType[$backendType][] = $attribute['attribute_id'];
                }
            }
            $this->eavAttributesByType = $eavAttributesByType;
        }
        return $this->eavAttributesByType;
    }

    /**
     * @return array
     */
    public function getSiteVariantAttributes(): array
    {
        if ($this->siteVariantAttributes === null) {
            $siteVariantAttributes = [];
            foreach ($this->getAllAttributes() as $attribute) {
                if ($attribute['append_site_variant']) {
                    $siteVariantAttributes[] = $attribute['attribute'];
                }
            }
            $this->siteVariantAttributes = array_unique($siteVariantAttributes);
        }
        return $this->siteVariantAttributes;
    }
}
