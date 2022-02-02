<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FullExport extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var \Aligent\FredhopperIndexer\Model\Export\FullExporter
     */
    protected $exporter;

    public function __construct(
        \Aligent\FredhopperIndexer\Model\Export\FullExporter $exporter,
        string $name = null
    ) {
        $this->exporter = $exporter;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('fredhopper:indexer:full_export')
            ->setDescription('Export full set of products to Fredhopper');

        $desc = 'If true, zip file will be generated, but no upload to FH will be performed';
        $this->addOption('dry-run', null, null, $desc);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->exporter->setDryRun($input->getOption('dry-run'));

        $this->exporter->export();
    }
}
