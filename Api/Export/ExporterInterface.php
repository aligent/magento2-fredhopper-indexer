<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Api\Export;

interface ExporterInterface
{
    /**
     * @return bool
     */
    public function export(): bool;
}
