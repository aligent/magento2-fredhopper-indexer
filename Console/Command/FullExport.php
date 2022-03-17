<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FullExport extends \Symfony\Component\Console\Command\Command
{
    const OPTION_DRY_RUN = 'dry-run';
    const OPTION_FORCE = 'force';

    /**
     * @var \Magento\Framework\App\State
     */
    protected $appState;

    /**
     * @var \Aligent\FredhopperIndexer\Model\Export\FullExporter
     */
    protected $exporter;

    /**
     * @var \Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface[]
     */
    protected $preExportValidators;

    /**
     * @param \Magento\Framework\App\State $appState
     * @param \Aligent\FredhopperIndexer\Model\Export\FullExporter $exporter
     * @param \Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface[] $preExportValidators
     * @param string|null $name
     */
    public function __construct(
        \Magento\Framework\App\State $appState,
        \Aligent\FredhopperIndexer\Model\Export\FullExporter $exporter,
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

    public function execute(InputInterface $input, OutputInterface $output)
    {
        try {
            $this->appState->getAreaCode();
        } catch (\Exception $e) {
            $this->appState->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
        }

        $this->exporter->setDryRun($input->getOption(self::OPTION_DRY_RUN));

        $force = $input->getOption(self::OPTION_FORCE);
        if (!$force) {
            foreach ($this->preExportValidators as $preExportValidator) {
                $preExportValidator->validateState();
            }
        }

        $this->exporter->export();
    }
}
