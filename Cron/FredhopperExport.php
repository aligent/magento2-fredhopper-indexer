<?php
namespace Aligent\FredhopperIndexer\Cron;

class FredhopperExport
{
    /**
     * @var \Aligent\FredhopperIndexer\Api\Export\ExporterInterface
     */
    protected $fredhopperExporter;
    /**
     * @var \Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface[]
     */
    protected $preExportValidators;

    /**
     * FredhopperExport constructor.
     * @param \Aligent\FredhopperIndexer\Api\Export\ExporterInterface $fredhopperExporter
     * @param \Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface[] $preExportValidators
     */
    public function __construct(
        \Aligent\FredhopperIndexer\Api\Export\ExporterInterface $fredhopperExporter,
        array $preExportValidators = []
    ) {
        $this->fredhopperExporter = $fredhopperExporter;
        $this->preExportValidators = $preExportValidators;
    }

    /**
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function export()
    {
        foreach ($this->preExportValidators as $preExportValidator) {
            $preExportValidator->validateState();
        }
        $this->fredhopperExporter->export();
    }
}
