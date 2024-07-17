<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron\Generate;

use Aligent\FredhopperIndexer\Model\Export\GenerateFullExport;

class Full
{
    /**
     * @param GenerateFullExport $generateFullExport
     */
    public function __construct(
        private readonly GenerateFullExport $generateFullExport
    ) {
    }

    /**
     * Generate a full product export
     *
     * @return void
     */
    public function execute(): void
    {
        $this->generateFullExport->execute();
    }
}
