<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\AbstractEntity;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Exception\LocalizedException;

class AttributeDataProvider
{
    private array $attributes;
    private array $attributesByBackendType;
    private array $attributeOptions = [];

    public function __construct(
        private readonly EavConfig $eavConfig,
        private readonly CollectionFactory $productAttributeCollectionFactory
    ) {
    }

    /**
     * Get all attributes that can be indexed
     *
     * @param $backendType
     * @return array
     * @throws LocalizedException
     */
    public function getIndexableAttributes($backendType = null): array
    {
        if (!isset($this->attributes)) {
            $this->attributes = [];

            $productAttributes = $this->productAttributeCollectionFactory->create();

            /** @var Attribute[] $attributes */
            $attributes = $productAttributes->getItems();

            /** @var AbstractEntity $entity */
            $entity = $this->eavConfig->getEntityType(Product::ENTITY)->getEntity();

            foreach ($attributes as $attribute) {
                $attribute->setEntity($entity);
                $this->attributes[$attribute->getAttributeId()] = $attribute;
                $this->attributes[$attribute->getAttributeCode()] = $attribute;
            }
        }

        if ($backendType !== null) {
            if (isset($this->attributesByBackendType[$backendType])) {
                return $this->attributesByBackendType[$backendType];
            }
            $this->attributesByBackendType[$backendType] = [];
            foreach ($this->attributes as $attribute) {
                if ($attribute->getBackendType() == $backendType) {
                    $this->attributesByBackendType[$backendType][$attribute->getAttributeId()] = $attribute;
                }
            }

            return $this->attributesByBackendType[$backendType];
        }

        return $this->attributes;
    }

    /**
     * Retrieves an attribute by its code
     *
     * @throws LocalizedException
     */
    public function getAttribute(string $attributeCode): Attribute
    {
        return $this->getAttributeModel($attributeCode);
    }

    /**
     * Retrieves an attribute by its ID
     *
     * @param int $attributeId
     * @return Attribute
     * @throws LocalizedException
     */
    public function getAttributeById(int $attributeId): Attribute
    {
        return $this->getAttributeModel($attributeId);
    }

    /**
     * Get consolidated attribute value
     *
     * @param int $attributeId
     * @param string|null $valueIds
     * @param int $storeId
     * @return string
     * @throws LocalizedException
     */
    public function getAttributeValue(int $attributeId, string|null $valueIds, int $storeId): string
    {
        $value = $valueIds;
        if (false !== $valueIds) {
            $optionValue = $this->getAttributeOptionValue($attributeId, $valueIds, $storeId);
            if (null === $optionValue) {
                $value = $this->filterAttributeValue($value);
            } else {
                $value = implode(',', array_filter([$value, $optionValue]));
            }
        }
        return $value;
    }

    /**
     *
     *
     * @param int $attributeId
     * @param string|null $valueIds
     * @param int $storeId
     * @return string|null
     * @throws LocalizedException
     */
    private function getAttributeOptionValue(int $attributeId, ?string $valueIds, int $storeId): ?string
    {
        $optionKey = $attributeId . '-' . $storeId;
        $attributeValueIds = $valueIds !== null ? explode(',', $valueIds) : [];
        $attributeOptionValue = '';

        if (!array_key_exists($optionKey, $this->attributeOptions)) {
            $attribute = $this->getAttributeModel($attributeId);
            $attribute->setData('store_id', $storeId);
            $options = $attribute->getSource()->toOptionArray();
            $this->attributeOptions[$optionKey] = array_column($options, 'label', 'value');
            foreach ($this->attributeOptions[$optionKey] as $id => $optionValue) {
                $this->attributeOptions[$optionKey][$id] = $this->filterAttributeValue($optionValue);
            }
        }

        foreach ($attributeValueIds as $attributeValueId) {
            if (isset($this->attributeOptions[$optionKey][$attributeValueId])) {
                $attributeOptionValue .= $this->attributeOptions[$optionKey][$attributeValueId] . ' ';
            }
        }
        return empty($attributeOptionValue) ? null : trim($attributeOptionValue);
    }

    /**
     * @param string|null $value
     * @return string
     */
    private function filterAttributeValue(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return preg_replace('/\s+iu', ' ', trim(strip_tags($value)));
    }

    /**
     * Retrieves the attribute instance by attribute code or id
     *
     * @param string|int $attributeKey
     * @return Attribute
     * @throws LocalizedException
     */
    private function getAttributeModel(string|int $attributeKey): Attribute
    {
        $attributes = $this->getIndexableAttributes();
        if (isset($attributes[$attributeKey])) {
            return $attributes[$attributeKey];
        }

        /** @var Attribute $attribute */
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $attributeKey);
        return $attribute;
    }
}
