<?php
namespace Aligent\FredhopperIndexer\Api\Export;

interface FileGeneratorInterface
{
    /**
     * Generate and save an additional file for export to Fredhopper
     * @param string $directory
     * @return string Full path of the generated file
     */
    public function generateFile(string $directory): string;
}
