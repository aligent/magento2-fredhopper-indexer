<?php

declare(strict_types=1);

namespace Aligent\FredhopperExport\Model\Validator;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Api\PreExportValidatorInterface;
use Aligent\FredhopperExport\Model\Config\SanityCheckConfig;
use Magento\Framework\Validation\ValidationException;

class DeletedProductsValidator implements PreExportValidatorInterface
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
        $maxDeletes = $this->sanityCheckConfig->getMaxDeleteProducts();

        if ($export->getProductDeleteCount() > $maxDeletes) {
            throw new ValidationException(
                __(
                    'Number of deleted products (%1) exceeds threshold (%3)',
                    $export->getProductDeleteCount(),
                    $maxDeletes
                )
            );
        }
    }
}
