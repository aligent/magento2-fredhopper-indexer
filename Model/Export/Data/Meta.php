<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field\FHAttributeTypes;
use Aligent\FredhopperIndexer\Helper\AgeAttributeConfig;
use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Aligent\FredhopperIndexer\Helper\CustomAttributeConfig;
use Aligent\FredhopperIndexer\Helper\ImageAttributeConfig;
use Aligent\FredhopperIndexer\Helper\PricingAttributeConfig;
use Aligent\FredhopperIndexer\Helper\StockAttributeConfig;
use Aligent\FredhopperIndexer\Model\RelevantCategory;
use Magento\Catalog\Model\Category;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;

class Meta
{
    public const ROOT_CATEGORY_NAME = 'catalog01';

    private RelevantCategory $relevantCategory;
    private GroupRepositoryInterface $customerGroupRepository;
    private SearchCriteriaBuilder $searchCriteriaBuilder;
    private AttributeConfig $attributeConfig;
    private PricingAttributeConfig $pricingAttributeConfig;
    private StockAttributeConfig $stockAttributeConfig;
    private AgeAttributeConfig $ageAttributeConfig;
    private ImageAttributeConfig $imageAttributeConfig;
    private CustomAttributeConfig $customAttributeConfig;

    private int $rootCategoryId = 1;

    public function __construct(
        RelevantCategory $relevantCategory,
        GroupRepositoryInterface $customerGroupRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        AttributeConfig $attributeConfig,
        PricingAttributeConfig $pricingAttributeConfig,
        StockAttributeConfig $stockAttributeConfig,
        AgeAttributeConfig $ageAttributeConfig,
        ImageAttributeConfig $imageAttributeConfig,
        CustomAttributeConfig $customAttributeConfig
    ) {
        $this->relevantCategory = $relevantCategory;
        $this->customerGroupRepository = $customerGroupRepository;
        $this->attributeConfig = $attributeConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->pricingAttributeConfig = $pricingAttributeConfig;
        $this->stockAttributeConfig = $stockAttributeConfig;
        $this->ageAttributeConfig = $ageAttributeConfig;
        $this->imageAttributeConfig = $imageAttributeConfig;
        $this->customAttributeConfig = $customAttributeConfig;
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
    private function getAttributesArray() : array
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
    private function getPriceAttributesArray(string $defaultLocale): array
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
        foreach ($this->customAttributeConfig->getCustomAttributeData() as $attributeCode => $attributeData) {
            if (($attributeData['type'] ?? null) === 'price') {
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
    private function getStockAttributesArray(string $defaultLocale): array
    {
        $stockAttributes = [];
        if ($this->stockAttributeConfig->getSendStockCount()) {
            $stockAttributes['stock_qty'] = 'Stock Count';
        }
        if ($this->stockAttributeConfig->getSendStockStatus()) {
            $stockAttributes['stock_status'] = 'Stock Status';
        }
        // check for any custom stock attributes
        foreach ($this->customAttributeConfig->getCustomAttributeData() as $attributeCode => $attributeData) {
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
    private function getImageAttributesArray(string $defaultLocale): array
    {
        $imageAttributes = [
            '_imageurl' => 'Image URL',
            '_thumburl' => 'Thumbnail URL'
        ];
        // check for custom image attributes
        foreach ($this->customAttributeConfig->getCustomAttributeData() as $attributeCode => $attributeData) {
            if (($attributeData['type'] ?? null) === 'image') {
                $imageAttributes[$attributeCode] = $attributeData['label'];
            }
        }

        $attributeArray = [];
        $suffixes = $this->imageAttributeConfig->getAllSiteVariantSuffixes();
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
    private function getAgeAttributesArray(string $defaultLocale): array
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
    private function getCustomAttributesArray(string $defaultLocale): array
    {
        $attributesArray = [];
        $siteVariantSuffixes = $this->attributeConfig->getAllSiteVariantSuffixes();
        foreach ($this->customAttributeConfig->getCustomAttributeData() as $customAttribute) {
            // check if attribute has already been processed as price/stock/image attribute
            if (!empty($customAttribute['type'])) {
                continue;
            }

            if ($customAttribute['is_site_variant']) {
                foreach ($siteVariantSuffixes as $siteVariantSuffix) {
                    $attributesArray[] = [
                        'attribute_id' => $customAttribute['attribute_code'] . $siteVariantSuffix,
                        'type' => $customAttribute['fredhopper_type'],
                        'names' => [
                            [
                                'locale' => $defaultLocale,
                                'name' => __($customAttribute['label'])
                            ]
                        ]
                    ];
                }
            } else {
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
        }
        return $attributesArray;
    }

    /**
     * @return string[]
     */
    private function getCategoryArray() : array
    {
        $categoryCollection = $this->relevantCategory->getCollection();

        $allCategories = $categoryCollection->getItems();

        /** @var Category $rootCategory */
        $this->rootCategoryId = $this->attributeConfig->getRootCategoryId();
        $rootCategory = $allCategories[$this->rootCategoryId] ?? null;
        return $this->getCategoryDataWithChildren($rootCategory, $allCategories);
    }

    /**
     * @param Category $category
     * @param array $allCategories
     * @return string[]
     */
    private function getCategoryDataWithChildren(
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
        $children = [];
        foreach (explode(',', $category->getChildren()) as $child) {
            if (!empty($child)) {
                $children[] = $child;
            }
        }
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
}
