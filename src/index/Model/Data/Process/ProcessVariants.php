<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Data\Process;

use Aligent\FredhopperCommon\Model\Config\AttributeConfig;
use Magento\Framework\Exception\LocalizedException;

class ProcessVariants
{
    /**
     * @param AttributeConfig $attributeConfig
     */
    public function __construct(
        private readonly AttributeConfig $attributeConfig
    ) {
    }

    /**
     * Process variant data
     *
     * @throws LocalizedException
     */
    public function execute(array $documents): array
    {
        $productAttributeCodes = [];
        foreach ($this->attributeConfig->getProductAttributeCodes(true) as $code) {
            $productAttributeCodes[$code] = true;
        }

        foreach ($documents as $productId => $documentData) {
            $documentData = $this->copyProductDataToVariants($documentData, $productId);
            $documentData = $this->removeVariantLevelAttributesFromParent($documentData, $productAttributeCodes);
            $documentData = $this->removeProductLevelAttributesFromVariants($documentData);

            $documents[$productId] = $documentData;
        }

        return $documents;
    }

    /**
     * Ensure that all products have at least one variant. For simple products, this is the product itself.
     *
     * @param $documentData
     * @param int $productId
     * @return array
     */
    private function copyProductDataToVariants($documentData, int $productId): array
    {
        if (empty($documentData['variants'])) {
            $documentData['variants'] =[
                $productId => $documentData['product']
            ];
        }
        return $documentData;
    }

    /**
     * Remove any variant-level attributes from parent product, ensuring it is set on each variant
     *
     * @param $documentData
     * @param array $productAttributeCodes
     * @return array
     */
    private function removeVariantLevelAttributesFromParent($documentData, array $productAttributeCodes): array
    {
        foreach ($documentData['product'] as $attributeCode => $productData) {
            if (in_array($attributeCode, $this->attributeConfig->getVariantAttributeCodes(true))) {
                $copyOfVariants = [];
                foreach ($documentData['variants'] as $variantId => $variantData) {
                    $variantData[$attributeCode] = $variantData[$attributeCode] ?? $productData;
                    $copyOfVariants[$variantId] = $variantData;
                }
                $documentData['variants'] = $copyOfVariants;
                if (!isset($productAttributeCodes[$attributeCode])) {
                    unset($documentData['product'][$attributeCode]);
                }
            }
        }
        return $documentData;
    }

    /**
     * Remove any product-level attributes from all variants
     *
     * @param $documentData
     * @return array
     */
    private function removeProductLevelAttributesFromVariants($documentData): array
    {
        $copyOfVariants = [];
        foreach ($documentData['variants'] as $variantId => $variantData) {
            foreach ($variantData as $attributeCode => $attributeValue) {
                if (!in_array($attributeCode, $this->attributeConfig->getVariantAttributeCodes(true))) {
                    unset($variantData[$attributeCode]);
                }
            }
            $copyOfVariants[$variantId] = $variantData;
        }
        $documentData['variants'] = $copyOfVariants;
        return $documentData;
    }
}
