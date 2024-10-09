<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Data\Process;

use Aligent\FredhopperCommon\Model\Data\AttributeDataProvider;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Elasticsearch\Model\Adapter\FieldType\Date as DateFieldType;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\App\Emulation;

class ProductMapper
{

    /**
     * @var string[]
     */
    private array $excludedAttributes;
    /**
     * @var array
     */
    private array $attributeOptionsCache;

    /**
     * List of attributes which will be skipped during mapping
     *
     * @var string[]
     */
    private array $defaultExcludedAttributes = [
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
    private array $attributesExcludedFromMerge = [
        'status',
        'visibility',
        'tax_class_id'
    ];

    /**
     * @param AttributeDataProvider $attributeDataProvider
     * @param DateFieldType $dateFieldType
     * @param Emulation $emulation
     * @param array $excludedAttributes
     */
    public function __construct(
        private readonly AttributeDataProvider $attributeDataProvider,
        private readonly DateFieldType $dateFieldType,
        private readonly Emulation $emulation,
        array $excludedAttributes = []
    ) {
        $this->excludedAttributes = array_merge($this->defaultExcludedAttributes, $excludedAttributes);
    }

    /**
     * Map product data
     *
     * @param array $indexData
     * @param int $productId
     * @param int $storeId
     * @param string $typeId
     * @param array $additionalFields
     * @return array
     * @throws LocalizedException
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
        foreach ($variantData as $variantId => $data) {
            foreach ($additionalFields[$variantId] ?? [] as $fieldId => $fieldValue) {
                $variantData[$variantId][$fieldId] = $fieldValue;
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
     * @throws LocalizedException
     */
    private function convertToProductData(int $productId, array $indexData, int $storeId): array
    {
        $productAttributes = [];

        if (isset($indexData['options'])) {
            // cover case with "options"
            // see \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider::prepareProductIndex
            $productAttributes['options'] = $indexData['options'];
            unset($indexData['options']);
        }

        foreach ($indexData as $attributeId => $attributeValues) {
            $attribute = $this->attributeDataProvider->getAttributeById($attributeId);
            if (in_array($attribute->getAttributeCode(), $this->excludedAttributes, true)) {
                continue;
            }
            if (!is_array($attributeValues)) {
                $attributeValues = [$productId => $attributeValues];
            }

            $attributeValues = $this->prepareAttributeValues($productId, $attribute, $attributeValues, $storeId);
            $productAttributes += $this->convertAttribute($attribute, $attributeValues, $storeId);
        }

        return $productAttributes;
    }

    /**
     * Convert data for attribute, add {attribute_code}_value for searchable attributes, that contain actual value.
     *
     * @param Attribute $attribute
     * @param array $attributeValues
     * @param int $storeId
     * @return array
     */
    private function convertAttribute(
        Attribute $attribute,
        array $attributeValues,
        int $storeId
    ): array {
        $attributeCode = $attribute->getAttributeCode();
        $combinedProductAttributes = [];

        $productIds = array_unique(array_keys($attributeValues));
        if (count($productIds) > 1) {
            foreach ($productIds as $productId) {
                $productAttributes = $this->convertAttribute(
                    $attribute,
                    [$productId => $attributeValues[$productId]],
                    $storeId
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
            if (!empty($retrievedValue)) {
                $combinedProductAttributes = [$attribute->getAttributeCode() => [$productId => $retrievedValue]];

                // boolean attributes should always be sent as 1/0, rather than Yes/No
                if ($attribute->getFrontendInput() !== 'boolean') {
                    $attributeLabels = $this->getValuesLabels(
                        $attribute,
                        $attributeValues,
                        $storeId,
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
    private function prepareAttributeValues(
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
    private function prepareMultiselectValues(array $values): array
    {
        $multiSelectValues = [];
        foreach ($values as $key => $value) {
            $multiSelectValues[$key] = explode(',', $value);
        }
        return $multiSelectValues;
    }

    /**
     * Is attribute date.
     *
     * @param Attribute $attribute
     * @return bool
     */
    private function isAttributeDate(Attribute $attribute): bool
    {
        return $attribute->getFrontendInput() === 'date'
            || in_array($attribute->getBackendType(), ['datetime', 'timestamp'], true);
    }

    /**
     * Get values labels.
     *
     * @param Attribute $attribute
     * @param array $attributeValues
     * @param int $storeId
     * @param bool $isMultiSelect
     * @return array
     */
    private function getValuesLabels(
        Attribute $attribute,
        array $attributeValues,
        int $storeId,
        bool $isMultiSelect
    ): array {
        $attributeLabels = [];

        $options = $this->getAttributeOptions($attribute, $storeId);
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
     * @param int $storeId
     * @return array
     */
    private function getAttributeOptions(Attribute $attribute, int $storeId): array
    {
        if (!isset($this->attributeOptionsCache[$storeId])) {
            $this->attributeOptionsCache[$storeId] = [];
        }
        if (!isset($this->attributeOptionsCache[$storeId][$attribute->getId()])) {
            $options = [];
            $this->emulation->startEnvironmentEmulation($storeId);
            foreach ($attribute->getOptions() ?? [] as $option) {
                $options[$option->getValue()] = (string)$option->getLabel();
            }
            $this->emulation->stopEnvironmentEmulation();
            $this->attributeOptionsCache[$storeId][$attribute->getId()] = $options;
        }

        return $this->attributeOptionsCache[$storeId][$attribute->getId()];
    }

    /**
     * Retrieve value for field. If the field has only one value this method will return it.
     * Otherwise, it will return an array of these values.
     * Note: array of values must have index keys, not as associative array.
     *
     * @param array $values
     * @return array|string
     */
    private function retrieveFieldValue(array $values): array|string
    {
        $values = array_unique($values);
        $allValues = [];
        foreach ($values as $value) {
            if ($value !== null) {
                $allValues[] = $value;
            }
        }

        return count($values) === 1 ? array_shift($allValues) : array_values($values);
    }
}
