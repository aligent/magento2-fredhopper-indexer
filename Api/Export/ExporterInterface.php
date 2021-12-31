<?php
namespace Aligent\FredhopperIndexer\Api\Export;

interface ExporterInterface
{
    public function export(): bool;

    public function setDryRun(bool $isDryRun): void;
}
