<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Indexer\Data\Process;

use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Magento\Framework\Exception\LocalizedException;

class ProcessVariants
{
    public function __construct(
        private readonly AttributeConfig $attributeConfig
    ) {
    }

    /**
     * @throws LocalizedException
     * [#array
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
     * @throws LocalizedException
     */
    private function removeVariantLevelAttributesFromParent($documentData, array $productAttributeCodes): array
    {
        foreach ($documentData['product'] as $attributeCode => $productData) {
            if (in_array($attributeCode, $this->attributeConfig->getVariantAttributeCodes())) {
                $copyOfVariants = [];
                foreach ($documentData['variants'] as $variantData) {
                    $variantData[$attributeCode] = $variantData[$attributeCode] ?? $productData;
                    $copyOfVariants[] = $variantData;
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
     * @throws LocalizedException
     */
    private function removeProductLevelAttributesFromVariants($documentData): array
    {
        $copyOfVariants = [];
        foreach ($documentData['variants'] as $variantData) {
            foreach ($variantData as $attributeCode => $attributeValue) {
                if (!in_array($attributeCode, $this->attributeConfig->getVariantAttributeCodes())) {
                    unset($variantData[$attributeCode]);
                }
            }
            $copyOfVariants[] = $variantData;
        }
        $documentData['variants'] = $copyOfVariants;
        return $documentData;
    }
}
