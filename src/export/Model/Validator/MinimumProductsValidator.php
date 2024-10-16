<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Validator;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Api\PreExportValidatorInterface;
use Aligent\FredhopperExport\Model\Config\SanityCheckConfig;
use Magento\Framework\Validation\ValidationException;

class MinimumProductsValidator implements PreExportValidatorInterface
{

    /**
     * @param SanityCheckConfig $sanityCheckConfig
     */
    public function __construct(
        private readonly SanityCheckConfig $sanityCheckConfig
    ) {
    }

    /**
     * @inheritDoc
     */
    public function validateState(ExportInterface $export): void
    {
        if ($export->getExportType() === ExportInterface::EXPORT_TYPE_FULL) {
            $minProducts = $this->sanityCheckConfig->getMinTotalProducts();
            if ($export->getProductCount() < $minProducts) {
                throw new ValidationException(
                    __(
                        'Total number of products (%1) does not meet threshold (%2)',
                       $export->getProductCount(),
                       $minProducts
                    ),
                );
            }
        }
    }
}
