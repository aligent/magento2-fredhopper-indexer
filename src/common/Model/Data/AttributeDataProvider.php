<?php

declare(strict_types=1);
namespace Aligent\FredhopperCommon\Model\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\AbstractEntity;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Exception\LocalizedException;

class AttributeDataProvider
{
    /**
     * @var array
     */
    private array $attributes;
    /**
     * @var array
     */
    private array $attributesByBackendType;

    /**
     * @param EavConfig $eavConfig
     * @param CollectionFactory $productAttributeCollectionFactory
     */
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
     */
    public function getIndexableAttributes($backendType = null): array
    {
        if (!isset($this->attributes)) {
            $this->attributes = [];

            $productAttributes = $this->productAttributeCollectionFactory->create();

            /** @var Attribute[] $attributes */
            $attributes = $productAttributes->getItems();

            /** @var AbstractEntity $entity */
            try {
                $entity = $this->eavConfig->getEntityType(Product::ENTITY)->getEntity();
            } catch (LocalizedException) {
                // this should never happen
                return [];
            }

            foreach ($attributes as $attribute) {
                $attribute->setEntity($entity);
                // ensure ids are integers
                $attribute->setAttributeId((int)$attribute->getAttributeId());
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
     * @param string|null $valueIds
     * @return string
     */
    public function getAttributeValue(string|null $valueIds): string
    {
        return $this->filterAttributeValue($valueIds);
    }

    /**
     * Filter attribute value
     *
     * @param string|null $value
     * @return string
     */
    private function filterAttributeValue(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return preg_replace('/\s+/iu', ' ', trim(strip_tags($value)));
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
