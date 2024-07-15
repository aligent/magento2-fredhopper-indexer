<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Api\Export;

use Aligent\FredhopperIndexer\Api\Export\Data\ExportInterface;
use Magento\Framework\Validation\ValidationException;

interface PreExportValidatorInterface
{
    /**
     * @param ExportInterface $export
     * @throws ValidationException
     */
    public function validateState(ExportInterface $export): void;
}
