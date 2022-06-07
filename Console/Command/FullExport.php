<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Console\Command;

use Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface;
use Aligent\FredhopperIndexer\Model\Export\FullExporter;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validation\ValidationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FullExport extends Command
{
    const OPTION_DRY_RUN = 'dry-run';
    const OPTION_FORCE = 'force';

    private State $appState;
    private FullExporter $exporter;

    /**
     * @var PreExportValidatorInterface[]
     */
    private array $preExportValidators;

    /**
     * @param State $appState
     * @param FullExporter $exporter
     * @param PreExportValidatorInterface[] $preExportValidators
     * @param string|null $name
     */
    public function __construct(
        State $appState,
        FullExporter $exporter,
        array $preExportValidators = [],
        string $name = null
    ) {
        $this->appState = $appState;
        $this->exporter = $exporter;
        $this->preExportValidators = $preExportValidators;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('fredhopper:indexer:full_export')
            ->setDescription('Export full set of products to Fredhopper');

        $desc = 'If true, zip file will be generated, but no upload to FH will be performed';
        $this->addOption(self::OPTION_DRY_RUN, null, null, $desc);

        $desc = 'Force export, ignoring sanity checks such as deletion threshold and minimum products per category';
        $this->addOption(self::OPTION_FORCE, null, null, $desc);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws LocalizedException
     * @throws FileSystemException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException $e) {
            try {
                $this->appState->setAreaCode(Area::AREA_ADMINHTML);
            } catch (LocalizedException $e) {
                // this shouldn't happen, but don't attempt to continue if it does
                $output->writeln('Could not set area code - aborting');
                return Cli::RETURN_FAILURE;
            }
        }

        $this->exporter->setDryRun($input->getOption(self::OPTION_DRY_RUN));

        $force = $input->getOption(self::OPTION_FORCE);
        $this->exporter->setForce($force);
        if (!$force) {
            $validationErrors = [];
            foreach ($this->preExportValidators as $preExportValidator) {
                try {
                    $preExportValidator->validateState();
                } catch (ValidationException $e) {
                    $validationErrors[] = $e->getMessage();
                }
            }
            if (!empty($validationErrors)) {
                $output->writeln('Export failed validation checks:');
                foreach ($validationErrors as $errorMessage) {
                    $output->writeln($errorMessage);
                }
                return Cli::RETURN_FAILURE;
            }
        }

        $this->exporter->export();
        return Cli::RETURN_SUCCESS;
    }
}
