<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron\Generate;

use Aligent\FredhopperIndexer\Model\Export\GenerateIncrementalExport;

class Incremental
{
    /**
     * @param GenerateIncrementalExport $generateIncrementalExport
     */
    public function __construct(
        private readonly GenerateIncrementalExport $generateIncrementalExport
    ) {
    }

    /**
     * Generate an incremental product export
     *
     * @return void
     */
    public function execute(): void
    {
        $this->generateIncrementalExport->execute();
    }
}
