<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Api\Export;

use Magento\Framework\Validation\ValidationException;

interface PreExportValidatorInterface
{
    /**
     * @throws ValidationException
     */
    public function validateState();
}
