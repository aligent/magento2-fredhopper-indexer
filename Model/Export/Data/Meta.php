<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field\FHAttributeTypes;
use Aligent\FredhopperIndexer\Helper\AgeAttributeConfig;
use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Aligent\FredhopperIndexer\Helper\PricingAttributeConfig;
use Aligent\FredhopperIndexer\Helper\StockAttributeConfig;
use Aligent\FredhopperIndexer\Model\RelevantCategory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;

class Meta
{
    public const ROOT_CATEGORY_NAME = 'catalog01';

    /**
     * @var RelevantCategory
     */
    protected $relevantCategory;
    /**
     * @var CategoryRepositoryInterface
     */
    protected $categoryRepository;
    /**
     * @var GroupRepositoryInterface
     */
    protected $customerGroupRepository;
    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    /**
     * @var AttributeConfig
     */
    protected $attributeConfig;
    /**
     * @var PricingAttributeConfig
     */
    protected $pricingAttributeConfig;
    /**
     * @var StockAttributeConfig
     */
    protected $stockAttributeConfig;
    /**
     * @var AgeAttributeConfig
     */
    protected $ageAttributeConfig;
    /**
     * @var array
     */
    protected $customAttributeData;

    /**
     * @var int
     */
    protected $rootCategoryId = 1;

    public function __construct(
        RelevantCategory $relevantCategory,
        CategoryRepositoryInterface $categoryRepository,
        GroupRepositoryInterface $customerGroupRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        AttributeConfig $attributeConfig,
        PricingAttributeConfig $pricingAttributeConfig,
        StockAttributeConfig $stockAttributeConfig,
        AgeAttributeConfig $ageAttributeConfig,
        $customAttributeData = []
    ) {
        $this->relevantCategory = $relevantCategory;
        $this->categoryRepository = $categoryRepository;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->attributeConfig = $attributeConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->pricingAttributeConfig = $pricingAttributeConfig;
        $this->stockAttributeConfig = $stockAttributeConfig;
        $this->ageAttributeConfig = $ageAttributeConfig;
        $this->customAttributeData = $customAttributeData;
    }

    /**
     * @return array
     * @throws LocalizedException
     */
    public function getMetaData() : array
    {
        $attributesArray = array_merge(
            $this->getAttributesArray(),
            [
                [
                    'attribute_id' => 'categories',
                    'type' => 'hierarchical',
                    'values' => [
                        $this->getCategoryArray()
                    ]
                ]
            ]
        );
        return [
            'meta' => [
                'attributes' => array_values($attributesArray)
            ]
        ];
    }

    /**
     * @throws LocalizedException
     */
    protected function getAttributesArray() : array
    {
        $attributeArray = [];
        $defaultLocale = $this->attributeConfig->getDefaultLocale();
        $siteVariantSuffixes = $this->attributeConfig->getAllSiteVariantSuffixes();
        /**
         * returns an array with the keys (only relevant keys listed):
         * attribute, fredhopper_type, label
         *
         */
        $allAttributes = $this->attributeConfig->getAllAttributes();
        foreach ($allAttributes as $attributeData) {
            if ($attributeData['append_site_variant']) {
                $suffixes = $siteVariantSuffixes;
            } else {
                $suffixes = [''];
            }
            foreach ($suffixes as $suffix) {
                $attributeArray[] = [
                    'attribute_id' => $attributeData['attribute'] . $suffix,
                    'type' => $attributeData['fredhopper_type'],
                    'names' => [
                        [
                            'locale' => $defaultLocale,
                            'name' => $attributeData['label']
                        ]
                    ]
                ];
            }
        }

        if ($this->attributeConfig->getUseSiteVariant()) {
            $attributeArray[] = [
                'attribute_id' => 'site_variant',
                'type' => FHAttributeTypes::ATTRIBUTE_TYPE_SET64,
                'names' => [
                    [
                        'locale' => $defaultLocale,
                        'name' => __('Site Variant')
                    ]
                ]
            ];
        }

        if ($this->pricingAttributeConfig->getUseCustomerGroup()) {
            $attributeArray[] = [
                'attribute_id' => 'customer_group',
                'type' => FHAttributeTypes::ATTRIBUTE_TYPE_SET,
                'names' => [
                    [
                        'locale' => $defaultLocale,
                        'name' => __('Customer Group')
                    ]
                ]
            ];
        }

        return array_merge(
            $attributeArray,
            $this->getPriceAttributesArray($defaultLocale),
            $this->getStockAttributesArray($defaultLocale),
            $this->getImageAttributesArray($defaultLocale),
            $this->getAgeAttributesArray($defaultLocale),
            $this->getCustomAttributesArray($defaultLocale)
        );
    }

    /**
     * @param string $defaultLocale
     * @return array
     * @throws LocalizedException
     */
    protected function getPriceAttributesArray(string $defaultLocale): array
    {
        $priceAttributes = [
            'regular_price' => 'Regular Price',
            'special_price' => 'Special Price'
        ];
        if ($this->pricingAttributeConfig->getUseRange()) {
            $priceAttributes['min_price'] = 'Minimum Price';
            $priceAttributes['max_price'] = 'Maximum Price';
        }
        // check for any custom attributes that are prices
        foreach ($this->customAttributeData as $attributeCode => $attributeData) {
            if (($attributeData['fredhopper_type'] ?? null) === 'price') {
                $priceAttributes[$attributeCode] = $attributeData['label'];
            }
        }

        $attributesArray = [];
        $siteVariantSuffixes = $this->pricingAttributeConfig->getAllSiteVariantSuffixes();
        $suffixes = [];
        $customerGroups = $this->customerGroupRepository->getList($this->searchCriteriaBuilder->create())->getItems();
        if ($this->pricingAttributeConfig->getUseCustomerGroup()) {
            foreach ($customerGroups as $customerGroup) {
                foreach ($siteVariantSuffixes as $siteVariantSuffix) {
                    $suffixes[] = '_' . $customerGroup->getId() . $siteVariantSuffix;
                }
            }
        } else {
            $suffixes = $siteVariantSuffixes;
        }

        foreach ($suffixes as $suffix) {
            foreach ($priceAttributes as $attributeCode => $label) {
                $attributesArray[] = [
                    'attribute_id' => $attributeCode . $suffix,
                    'type' => FHAttributeTypes::ATTRIBUTE_TYPE_FLOAT,
                    'names' => [
                        [
                            'locale' => $defaultLocale,
                            'name' => __($label)
                        ]
                    ]
                ];
            }
        }

        return $attributesArray;
    }

    /**
     * @param string $defaultLocale
     * @return array
     */
    protected function getStockAttributesArray(string $defaultLocale): array
    {
        $stockAttributes = [];
        if ($this->stockAttributeConfig->getSendStockCount()) {
            $stockAttributes['stock_qty'] = 'Stock Count';
        }
        if ($this->stockAttributeConfig->getSendStockStatus()) {
            $stockAttributes['stock_status'] = 'Stock Status';
        }
        // check for any custom stock attributes
        foreach ($this->customAttributeData as $attributeCode => $attributeData) {
            if (($attributeData['type'] ?? null) === 'stock') {
                $stockAttributes[$attributeCode] = $attributeData['label'];
            }
        }

        $attributesArray = [];
        $siteVariantSuffixes = $this->stockAttributeConfig->getAllSiteVariantSuffixes();

        foreach ($siteVariantSuffixes as $siteVariantSuffix) {
            foreach ($stockAttributes as $attributeCode => $label) {
                $attributesArray[] = [
                    'attribute_id' => $attributeCode . $siteVariantSuffix,
                    'type' => FHAttributeTypes::ATTRIBUTE_TYPE_INT,
                    'names' => [
                        [
                            'locale' => $defaultLocale,
                            'name' => __($label)
                        ]
                    ]
                ];
            }
        }

        return $attributesArray;
    }

    /**
     * @param string $defaultLocale
     * @return array
     */
    protected function getImageAttributesArray(string $defaultLocale): array
    {
        $imageAttributes = [
            '_imageurl' => 'Image URL',
            '_thumburl' => 'Thumbnail URL'
        ];
        // check for custom image attributes
        foreach ($this->customAttributeData as $attributeCode => $attributeData) {
            if (($attributeData['type'] ?? null) === 'image') {
                $imageAttributes[$attributeCode] = $attributeData['label'];
            }
        }

        $attributeArray = [];
        $suffixes = $this->attributeConfig->getAllSiteVariantSuffixes();
        foreach ($suffixes as $suffix) {
            foreach ($imageAttributes as $attributeCode => $label) {
                $attributeArray[] = [
                    'attribute_id' => $attributeCode . $suffix,
                    'type' => FHAttributeTypes::ATTRIBUTE_TYPE_ASSET,
                    'names' => [
                        [
                            'locale' => $defaultLocale,
                            'name' => __($label)
                        ]
                    ]
                ];
            }
        }
        return $attributeArray;
    }

    /**
     * @param string $defaultLocale
     * @return array
     */
    protected function getAgeAttributesArray(string $defaultLocale): array
    {
        $ageAttributes = [];
        if ($this->ageAttributeConfig->getSendNewIndicator()) {
            $ageAttributes['is_new'] = 'New';
        }
        if ($this->ageAttributeConfig->getSendDaysOnline()) {
            $ageAttributes['days_online'] = 'Newest Products'; // label used for sorting
        }

        $attributesArray = [];
        $siteVariantSuffixes = $this->ageAttributeConfig->getAllSiteVariantSuffixes();
        foreach ($siteVariantSuffixes as $siteVariantSuffix) {
            foreach ($ageAttributes as $attributeCode => $label) {
                $attributesArray[] = [
                    'attribute_id' => $attributeCode . $siteVariantSuffix,
                    'type' => FHAttributeTypes::ATTRIBUTE_TYPE_INT,
                    'names' => [
                        [
                            'locale' => $defaultLocale,
                            'name' => __($label)
                        ]
                    ]
                ];
            }
        }

        return $attributesArray;
    }

    /**
     * @param string $defaultLocale
     * @return array
     */
    protected function getCustomAttributesArray(string $defaultLocale): array
    {
        $attributesArray = [];
        foreach ($this->customAttributeData as $customAttribute) {
            // check if attribute has already been processed as price/stock/image attribute
            if (!empty($customAttribute['type'])) {
                continue;
            }
            $attributesArray[] = [
                'attribute_id' => $customAttribute['attribute_code'],
                'type' => $customAttribute['fredhopper_type'],
                'names' => [
                    [
                        'locale' => $defaultLocale,
                        'name' => __($customAttribute['label'])
                    ]
                ]
            ];
        }
        return $attributesArray;
    }

    protected function getCategoryArray() : array
    {
        $categoryCollection = $this->relevantCategory->getCollection();

        $allCategories = $categoryCollection->getItems();

        /** @var Category $rootCategory */
        $this->rootCategoryId = $this->attributeConfig->getRootCategoryId();
        $rootCategory = $allCategories[$this->rootCategoryId] ?? null;
        return $this->getCategoryDataWithChildren($rootCategory, $allCategories);
    }

    protected function getCategoryDataWithChildren(
        Category $category,
        array $allCategories
    ) : array {
        $categoryId = $category->getId();
        $categoryData = [
            'category_id' => ($categoryId == $this->rootCategoryId ? self::ROOT_CATEGORY_NAME : $categoryId)
        ];
        $names =[
            [
                'locale' => $this->attributeConfig->getDefaultLocale(),
                'name' => $allCategories[$category->getId()]->getName()
            ]
        ];
        $categoryData['names'] = $names;

        // add child category information
        $children = array_filter(
            explode(',', $category->getChildren()),
            function ($id) {
                return !empty($id);
            }
        );
        if (empty($children)) {
            $categoryData['children'] = [];
        } else {
            $childArray = [];
            foreach ($children as $childId) {
                if (!isset($allCategories[$childId])) {
                    continue;
                }
                $childCategory = $allCategories[$childId];
                $childArray[] = $this->getCategoryDataWithChildren($childCategory, $allCategories);
            }
            $categoryData['children'] = $childArray;
        }
        return $categoryData;
    }

    /**
     * @return array
     */
    public function getCustomAttributeData(): array
    {
        return $this->customAttributeData;
    }
}
