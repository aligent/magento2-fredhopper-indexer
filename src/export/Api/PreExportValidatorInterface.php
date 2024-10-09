<?php

declare(strict_types=1);

namespace Aligent\FredhopperExport\Api;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Framework\Validation\ValidationException;

interface PreExportValidatorInterface
{
    /**
     * Validate export state
     *
     * @param ExportInterface $export
     * @throws ValidationException
     */
    public function validateState(ExportInterface $export): void;
}
