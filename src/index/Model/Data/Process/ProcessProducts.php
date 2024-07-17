<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Data\Process;

use Aligent\FredhopperCommon\Model\Config\AttributeConfig;
use Magento\Framework\Exception\LocalizedException;

class ProcessProducts
{

    /**
     * @param AttributeConfig $attributeConfig
     */
    public function __construct(
        private readonly AttributeConfig $attributeConfig
    ) {
    }

    /**
     * Process product data, rolling up variant data to the parent level
     *
     * @param array $documents
     * @return array
     * @throws LocalizedException
     */
    public function execute(array $documents): array
    {
        // need to collate variant level attributes at product level
        // keep them at variant level also - variant data won't be sent, but can be used to trigger resending
        // of parent data
        foreach ($documents as $key => $documentData) {
            foreach ($this->attributeConfig->getVariantAttributeCodes(true) as $variantAttributeCode) {
                $documents[$key] = $this->processProductVariantAttribute($documentData, $variantAttributeCode);
            }
        }
        return $documents;
    }

    /**
     * Collates the variant-level values for a single attribute
     *
     * @param array $data passed by reference
     * @param string $variantAttributeCode
     * @return array
     */
    private function processProductVariantAttribute(array $data, string $variantAttributeCode): array
    {
        // convert product attribute to an array if it's not already
        if (isset($data['product'][$variantAttributeCode]) &&
            !is_array($data['product'][$variantAttributeCode])) {
            $data['product'][$variantAttributeCode] = [$data['product'][$variantAttributeCode]];
        }
        $valueArray = [];
        foreach ($data['variants'] as $variantData) {
            if (isset($variantData[$variantAttributeCode])) {
                $value = $variantData[$variantAttributeCode];
                $valueArray[] = is_array($value) ? $value : [$value];
            }
        }
        $valueArray = array_merge([], ...$valueArray);

        // if there are variant values to include, ensure product value is set
        if (!empty($valueArray)) {
            $data['product'][$variantAttributeCode] = $data['product'][$variantAttributeCode] ?? [];
            $data['product'][$variantAttributeCode] = array_merge(
                $data['product'][$variantAttributeCode],
                $valueArray
            );
        }
        return $data;
    }
}
