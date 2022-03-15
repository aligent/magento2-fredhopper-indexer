<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Cron;

use Aligent\FredhopperIndexer\Api\Export\ExporterInterface;
use Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface;
use Aligent\FredhopperIndexer\Helper\Email;
use Aligent\FredhopperIndexer\Helper\SanityCheckConfig;
use Magento\Framework\Validation\ValidationException;

class FredhopperExport
{
    /**
     * @var ExporterInterface
     */
    protected $fredhopperExporter;

    /**
     * @var SanityCheckConfig
     */
    protected $sanityConfig;

    /**
     * @var Email
     */
    protected $emailHelper;

    /**
     * @var PreExportValidatorInterface[]
     */
    protected $preExportValidators;

    /**
     * FredhopperExport constructor.
     * @param ExporterInterface $fredhopperExporter
     * @param SanityCheckConfig $sanityConfig
     * @param Email $emailHelper
     * @param PreExportValidatorInterface[] $preExportValidators
     */
    public function __construct(
        ExporterInterface $fredhopperExporter,
        SanityCheckConfig $sanityConfig,
        Email $emailHelper,
        array $preExportValidators = []
    ) {
        $this->fredhopperExporter = $fredhopperExporter;
        $this->sanityConfig = $sanityConfig;
        $this->emailHelper = $emailHelper;
        $this->preExportValidators = $preExportValidators;
    }

    /**
     * @throws ValidationException
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
