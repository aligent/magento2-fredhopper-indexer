<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron\Generate;

use Aligent\FredhopperExport\Model\GenerateSuggestExport;

class Suggest
{
    public function __construct(
        private readonly GenerateSuggestExport $generateSuggestExport
    ) {
    }

    public function execute(): void
    {
        $this->generateSuggestExport->execute();
    }
}
