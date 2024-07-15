<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export\Validator;

use Aligent\FredhopperIndexer\Api\Export\Data\ExportInterface;
use Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface;
use Aligent\FredhopperIndexer\Helper\SanityCheckConfig;
use Magento\Framework\Validation\ValidationException;

class MinimumProductsValidator implements PreExportValidatorInterface
{

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
                        'Total number of products (%1) does not meet threshold (%3)',
                       $export->getProductCount(),
                       $minProducts
                    ),
                );
            }
        }
    }
}
