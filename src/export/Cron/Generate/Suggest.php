<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron\Generate;

use Aligent\FredhopperExport\Model\GenerateSuggestExport;

class Suggest
{
    /**
     * @param GenerateSuggestExport $generateSuggestExport
     */
    public function __construct(
        private readonly GenerateSuggestExport $generateSuggestExport
    ) {
    }

    /**
     * Generate a suggest export
     *
     * @return void
     */
    public function execute(): void
    {
        $this->generateSuggestExport->execute();
    }
}
