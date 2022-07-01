<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model;

class AttributeTranslator
{

    /**
     * Array of existing attribute codes as keys and their replacement codes as values
     *
     * @var string[]
     */
    private array $attributeCodeTranslations;

    /**
     * Array of existing attribute codes as keys and their replacement labels as values
     *
     * @var string[]
     */
    private array $attributeLabelTranslations;

    /**
     * @param array $attributeCodeTranslations
     * @param array $attributeLabelTranslations
     */
    public function __construct(
        array $attributeCodeTranslations = [],
        array $attributeLabelTranslations = []
    ) {
        $this->attributeCodeTranslations = $attributeCodeTranslations;
        $this->attributeLabelTranslations = $attributeLabelTranslations;
    }

    public function getTranslatedAttributeCode(string $attributeCode) : string
    {
        return $this->attributeCodeTranslations[$attributeCode] ?? $attributeCode;
    }

    public function getTranslatedAttributLabelForCode(string $attributeCode, string $originalLabel): string
    {
        return $this->attributeLabelTranslations[$attributeCode] ?? $originalLabel;
    }
}
