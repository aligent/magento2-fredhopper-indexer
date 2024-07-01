<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Block\Adminhtml\Form\Field\FHAttributeTypes;
use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Aligent\FredhopperIndexer\Helper\CustomAttributeConfig;
use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Aligent\FredhopperIndexer\Model\RelevantCategory;
use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\LocalizedException;

class Meta
{
    public const ROOT_CATEGORY_NAME = 'catalog01';

    private int $rootCategoryId = 1;

    public function __construct(
        private readonly RelevantCategory $relevantCategory,
        private readonly GeneralConfig $generalConfig,
        private readonly AttributeConfig $attributeConfig,
        private readonly CustomAttributeConfig $customAttributeConfig
    ) {
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
        $defaultLocale = $this->generalConfig->getDefaultLocale();
        $siteVariantSuffixes = $this->generalConfig->getAllSiteVariantSuffixes();
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
            foreach ($suffixes as $siteVariant => $suffix) {
                $attributeArray[] = [
                    'attribute_id' => $attributeData['attribute'] . $suffix,
                    'type' => $attributeData['fredhopper_type'],
                    'names' => [
                        [
                            'locale' => $defaultLocale,
                            'name' => $attributeData['label'] . (is_numeric($siteVariant) ? '' : (' ' . $siteVariant))
                        ]
                    ]
                ];
            }
        }

        if ($this->generalConfig->getUseSiteVariant()) {
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

        return array_merge(
            $attributeArray,
            $this->getCustomAttributesArray($defaultLocale)
        );
    }

    /**
     * @param string $defaultLocale
     * @return array
     */
    private function getCustomAttributesArray(string $defaultLocale): array
    {
        $attributesArray = [];
        $siteVariantSuffixes = $this->generalConfig->getAllSiteVariantSuffixes();
        foreach ($this->customAttributeConfig->getCustomAttributeData() as $customAttribute) {
            // check if attribute has already been processed as price/stock/image attribute
            if (!empty($customAttribute['type'])) {
                continue;
            }

            if ($customAttribute['is_site_variant'] ?? false) {
                foreach ($siteVariantSuffixes as $siteVariant => $siteVariantSuffix) {
                    $attributesArray[] = [
                        'attribute_id' => $customAttribute['attribute_code'] . $siteVariantSuffix,
                        'type' => $customAttribute['fredhopper_type'],
                        'names' => [
                            [
                                'locale' => $defaultLocale,
                                'name' => __($customAttribute['label']) .
                                    (is_numeric($siteVariant) ? '' : (' ' . $siteVariant))
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
     * @throws LocalizedException
     */
    private function getCategoryArray() : array
    {
        $categoryCollection = $this->relevantCategory->getCollection();

        $allCategories = $categoryCollection->getItems();

        /** @var Category $rootCategory */
        $this->rootCategoryId = $this->generalConfig->getRootCategoryId();
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
                'locale' => $this->generalConfig->getDefaultLocale(),
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
