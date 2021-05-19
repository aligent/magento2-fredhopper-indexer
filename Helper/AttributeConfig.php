<?php
namespace Aligent\FredhopperIndexer\Helper;

class AttributeConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/product_config/';
    public const XML_PATH_USE_VARIANT_PRODUCTS = self::XML_PATH_PREFIX . 'use_variant_products';
    public const XML_PATH_PRODUCT_ATTRIBUTES = self::XML_PATH_PREFIX . 'product_attributes';
    public const XML_PATH_VARIANT_ATTRIBUTES = self::XML_PATH_PREFIX . 'variant_attributes';

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $json;
    /**
     * @var \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider
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
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Locale\Resolver $localeResolver,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Serialize\Serializer\Json $json,
        \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider $dataProvider
    ) {
        parent::__construct($context, $localeResolver, $storeManager);
        $this->json = $json;
        $this->dataProvider = $dataProvider;
    }

    public function getUseVariantProducts()
    {
        if ($this->useVariantProducts === null) {
            $this->useVariantProducts = $this->scopeConfig->isSetFlag(self::XML_PATH_USE_VARIANT_PRODUCTS);
        }
        return $this->useVariantProducts;
    }

    public function getProductAttributes()
    {
        if ($this->productAttributes === null) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_PRODUCT_ATTRIBUTES);
            $productAttributes = $this->json->unserialize($configValue) ?? [];
            $this->addMagentoAttributeData($productAttributes);
            $this->productAttributes = $productAttributes;
        }
        return $this->productAttributes;
    }

    public function getVariantAttributes()
    {
        if ($this->variantAttributes === null) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_VARIANT_ATTRIBUTES);
            $variantAttributes = $this->json->unserialize($configValue) ?? [];
            $this->addMagentoAttributeData($variantAttributes);
            $this->variantAttributes = $variantAttributes;
        }

        return $this->variantAttributes;
    }

    public function getAllAttributes()
    {
        if ($this->allAttributes === null) {
            $this->allAttributes = array_merge($this->getProductAttributes(), $this->getVariantAttributes());
        }
        return $this->allAttributes;
    }

    public function getBooleanAttributes()
    {
        if ($this->booleanAttributes === null) {
            $this->booleanAttributes = array_filter($this->getAllAttributes(), function ($attribute) {
                return isset($attribute['frontend_input']) && $attribute['frontend_input'] === 'boolean';
            });
        }
        return $this->booleanAttributes;
    }

    public function getVariantAttributeCodes()
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

    protected function getSearchableAttributes()
    {
        if ($this->searchableAttributes === null) {
            $this->searchableAttributes = $this->dataProvider->getSearchableAttributes();
        }
        return $this->searchableAttributes;
    }

    protected function addMagentoAttributeData(array &$attributesConfig)
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

    public function getStaticAttributes()
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

    public function getAttributesWithFredhopperType()
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

    public function getEavAttributesByType()
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

    public function getSiteVariantAttributes()
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
