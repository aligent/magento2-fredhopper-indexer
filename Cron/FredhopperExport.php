<?php
namespace Aligent\FredhopperIndexer\Cron;

use Magento\Framework\Validation\ValidationException;

class FredhopperExport
{
    /**
     * @var \Aligent\FredhopperIndexer\Api\Export\ExporterInterface
     */
    protected $fredhopperExporter;

    /**
     * @var \Aligent\FredhopperIndexer\Helper\SanityCheckConfig
     */
    protected $sanityConfig;

    /**
     * @var \Aligent\FredhopperIndexer\Helper\Email
     */
    protected $emailHelper;

    /**
     * @var \Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface[]
     */
    protected $preExportValidators;

    /**
     * FredhopperExport constructor.
     * @param \Aligent\FredhopperIndexer\Api\Export\ExporterInterface $fredhopperExporter
     * @param \Aligent\FredhopperIndexer\Helper\SanityCheckConfig $sanityConfig
     * @param \Aligent\FredhopperIndexer\Helper\Email $emailHelper
     * @param \Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface[] $preExportValidators
     */
    public function __construct(
        \Aligent\FredhopperIndexer\Api\Export\ExporterInterface $fredhopperExporter,
        \Aligent\FredhopperIndexer\Helper\SanityCheckConfig $sanityConfig,
        \Aligent\FredhopperIndexer\Helper\Email $emailHelper,
        array $preExportValidators = []
    ) {
        $this->fredhopperExporter = $fredhopperExporter;
        $this->sanityConfig = $sanityConfig;
        $this->emailHelper = $emailHelper;
        $this->preExportValidators = $preExportValidators;
    }

    /**
     * @throws \Magento\Framework\Validation\ValidationException
     */
    public function export()
    {
        try {
            foreach ($this->preExportValidators as $preExportValidator) {
                $preExportValidator->validateState();
            }
        } catch (ValidationException $ex) {
            $recipients = $this->sanityConfig->getErrorEmailRecipients();
            if (count($recipients) > 0) {
                $this->emailHelper->sendErrorEmail($recipients, [$ex->getMessage()]);
            }
            throw $ex;
        }

        $this->fredhopperExporter->export();
    }
}
