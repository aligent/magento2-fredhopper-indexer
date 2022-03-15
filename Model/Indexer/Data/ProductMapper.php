<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Magento\Catalog\Model\Product\Visibility;
use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Elasticsearch\Model\Adapter\FieldType\Date as DateFieldType;

class ProductMapper
{
    /**
     * @var Visibility
     */
    protected $visibility;

    /**
     * @var DataProvider
     */
    protected $dataProvider;

    /**
     * @var array
     */
    private $attributeOptionsCache;

    /**
     * @var DateFieldType
     */
    protected $dateFieldType;

    /**
     * @var array
     */
    private $excludedAttributes;

    /**
     * List of attributes which will be skipped during mapping
     *
     * @var string[]
     */
    private $defaultExcludedAttributes = [
        'price',
        'media_gallery',
        'tier_price',
        'quantity_and_stock_status',
        'media_gallery',
        'giftcard_amounts',
    ];

    /**
     * @var string[]
     */
    private $attributesExcludedFromMerge = [
        'status',
        'visibility',
        'tax_class_id'
    ];

    /**
     * ProductMapper constructor.
     * @param Visibility $visibility
     * @param DataProvider $dataProvider
     * @param DateFieldType $dateFieldType
     * @param array $excludedAttributes
     */
    public function __construct(
        Visibility $visibility,
        DataProvider $dataProvider,
        DateFieldType $dateFieldType,
        array $excludedAttributes = []
    ) {
        $this->visibility = $visibility;
        $this->dataProvider = $dataProvider;
        $this->dateFieldType = $dateFieldType;
        $this->excludedAttributes = array_merge($this->defaultExcludedAttributes, $excludedAttributes);
    }

    /**
     * @param array $indexData
     * @param int $productId
     * @param int $storeId
     * @param string $typeId
     * @param array $additionalFields
     * @return array
     */
    public function mapProduct(
        array $indexData,
        int $productId,
        int $storeId,
        string $typeId,
        array $additionalFields
    ) : array {

        $productIndexData = $this->convertToProductData($productId, $indexData, $storeId);

        $productData = [];
        $variantData = [];

        foreach ($productIndexData as $attributeCode => $mappedProductData) {
            // separate into product data and variant data
            foreach ($mappedProductData as $id => $value) {
                if ($productId === $id) {
                    $productData[$attributeCode] = $value;
                } elseif ($typeId === Configurable::TYPE_CODE) {
                    // only want to have variant information for configurable products
                    // map variant id to parent for use later
                    $variantData[$id] = $variantData[$id] ?? [];
                    $variantData[$id][$attributeCode] = $value;
                }
            }
        }
        foreach ($additionalFields[$productId] ?? [] as $fieldId => $fieldValue) {
            $productData[$fieldId] = $fieldValue;
        }
        foreach ($variantData as $variantId => &$data) {
            foreach ($additionalFields[$variantId] ?? [] as $fieldId => $fieldValue) {
                $data[$fieldId] = $fieldValue;
            }
        }

        return [
            'product' => $productData,
            'variants' => $variantData
        ];
    }

    /**
     * Convert raw data retrieved from source tables to human-readable format.
     *
     * @param int $productId
     * @param array $indexData
     * @param int $storeId
     * @return array
     */
    protected function convertToProductData(int $productId, array $indexData, int $storeId): array
    {
        $productAttributes = [];

        if (isset($indexData['options'])) {
            // cover case with "options"
            // see \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider::prepareProductIndex
            $productAttributes['options'] = $indexData['options'];
            unset($indexData['options']);
        }

        foreach ($indexData as $attributeId => $attributeValues) {
            $attribute = $this->dataProvider->getSearchableAttribute($attributeId);
            if (in_array($attribute->getAttributeCode(), $this->excludedAttributes, true)) {
                continue;
            }
            if (!is_array($attributeValues)) {
                $attributeValues = [$productId => $attributeValues];
            }

            $attributeValues = $this->prepareAttributeValues($productId, $attribute, $attributeValues, $storeId);
            $productAttributes += $this->convertAttribute($attribute, $attributeValues);
        }

        return $productAttributes;
    }

    /**
     * Convert data for attribute, add {attribute_code}_value for searchable attributes, that contain actual value.
     *
     * @param Attribute $attribute
     * @param array $attributeValues
     * @return array
     */
    protected function convertAttribute(
        Attribute $attribute,
        array $attributeValues
    ): array {
        $attributeCode = $attribute->getAttributeCode();
        $combinedProductAttributes = [];

        $productIds = array_unique(array_keys($attributeValues));
        if (count($productIds) > 1) {
            foreach ($productIds as $productId) {
                $productAttributes = $this->convertAttribute(
                    $attribute,
                    [$productId => $attributeValues[$productId]]
                );
                $attributeValue = '';
                if (isset($productAttributes[$attributeCode][$productId])) {
                    $attributeValue = $productAttributes[$attributeCode][$productId];
                }
                $combinedProductAttributes[$attributeCode][$productId] = $attributeValue;
            }
        } else {
            $productId = reset($productIds);
            $retrievedValue = $this->retrieveFieldValue($attributeValues);
            if ($retrievedValue) {
                $combinedProductAttributes = [$attribute->getAttributeCode() => [$productId => $retrievedValue]];

                // boolean attributes should always be sent as 1/0, rather than Yes/No
                if ($attribute->getFrontendInput() !== 'boolean') {
                    $attributeLabels = $this->getValuesLabels(
                        $attribute,
                        $attributeValues,
                        $attribute->getFrontendInput() === 'multiselect'
                    );
                    $retrievedLabel = $this->retrieveFieldValue($attributeLabels);
                    // if there is a label for the attribute value(s), then use those, as they will potentially be used
                    // in facets, etc. which are customer-facing
                    if ($retrievedLabel !== []) {
                        $combinedProductAttributes[$attribute->getAttributeCode()] = [$productId => $retrievedLabel];
                    }
                }
            }
        }

        return $combinedProductAttributes;
    }

    /**
     * Prepare attribute values.
     *
     * @param int $productId
     * @param Attribute $attribute
     * @param array $attributeValues
     * @param int $storeId
     * @return array
     */
    protected function prepareAttributeValues(
        int $productId,
        Attribute $attribute,
        array $attributeValues,
        int $storeId
    ): array {
        if (in_array($attribute->getAttributeCode(), $this->attributesExcludedFromMerge, true)) {
            $attributeValues = [
                $productId => $attributeValues[$productId] ?? '',
            ];
        }

        if ($attribute->getFrontendInput() === 'multiselect') {
            $attributeValues = $this->prepareMultiselectValues($attributeValues);
        }

        if ($this->isAttributeDate($attribute)) {
            foreach ($attributeValues as $key => $attributeValue) {
                $attributeValues[$key] = $this->dateFieldType->formatDate($storeId, $attributeValue);
            }
        }

        return $attributeValues;
    }

    /**
     * Prepare multiselect values.
     *
     * @param array $values
     * @return array
     */
    protected function prepareMultiselectValues(array $values): array
    {
        return array_map(function (string $value) {
            return explode(',', $value);
        }, $values);
    }

    /**
     * Is attribute date.
     *
     * @param Attribute $attribute
     * @return bool
     */
    protected function isAttributeDate(Attribute $attribute): bool
    {
        return $attribute->getFrontendInput() === 'date'
            || in_array($attribute->getBackendType(), ['datetime', 'timestamp'], true);
    }

    /**
     * Get values labels.
     *
     * @param Attribute $attribute
     * @param array $attributeValues
     * @param bool $isMultiSelect
     * @return array
     */
    protected function getValuesLabels(
        Attribute $attribute,
        array $attributeValues,
        bool $isMultiSelect
    ): array {
        $attributeLabels = [];

        $options = $this->getAttributeOptions($attribute);
        if (empty($options)) {
            return $attributeLabels;
        }

        // multiselect attributes will be an array of arrays
        if ($isMultiSelect) {
            $values = [];
            foreach ($attributeValues as $valueArray) {
                $values[] = $valueArray;
            }
            $attributeValues = array_unique(array_merge([], ...$values));
        }

        foreach ($attributeValues as $attributeValue) {
            if (isset($options[$attributeValue])) {
                $attributeLabels[] = $options[$attributeValue];
            }
        }

        return $attributeLabels;
    }

    /**
     * Retrieve options for attribute
     *
     * @param Attribute $attribute
     * @return array
     */
    protected function getAttributeOptions(Attribute $attribute): array
    {
        if (!isset($this->attributeOptionsCache[$attribute->getId()])) {
            $options = [];
            foreach ($attribute->getOptions() ?? [] as $option) {
                $options[$option->getValue()] = $option->getLabel();
            }
            $this->attributeOptionsCache[$attribute->getId()] = $options;
        }

        return $this->attributeOptionsCache[$attribute->getId()];
    }

    /**
     * Retrieve value for field. If field have only one value this method return it.
     * Otherwise, will be returned array of these values.
     * Note: array of values must have index keys, not as associative array.
     *
     * @param array $values
     * @return array|string
     */
    protected function retrieveFieldValue(array $values)
    {
        $values = array_unique($values);
        $values = array_filter($values, function ($el) {
            return $el !== null;
        });

        return count($values) === 1 ? array_shift($values) : array_values($values);
    }
}
