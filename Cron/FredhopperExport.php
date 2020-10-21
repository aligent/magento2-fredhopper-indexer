<?php
namespace Aligent\FredhopperIndexer\Cron;

class FredhopperExport
{
    /**
     * @var \Aligent\FredhopperIndexer\Api\Export\ExporterInterface
     */
    protected $fredhopperExporter;

    public function __construct(
        \Aligent\FredhopperIndexer\Api\Export\ExporterInterface $fredhopperExporter
    ) {
        $this->fredhopperExporter = $fredhopperExporter;
    }

    public function export()
    {
        $this->fredhopperExporter->export();
    }
}
