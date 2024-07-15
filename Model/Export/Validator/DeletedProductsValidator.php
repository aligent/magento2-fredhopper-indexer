<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Validator;

use Aligent\FredhopperIndexer\Api\Export\Data\ExportInterface;
use Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface;
use Aligent\FredhopperIndexer\Helper\SanityCheckConfig;
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
