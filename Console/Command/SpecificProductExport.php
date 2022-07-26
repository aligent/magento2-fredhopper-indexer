<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Console\Command;

use Aligent\FredhopperIndexer\Api\Export\ExporterInterface;
use Aligent\FredhopperIndexer\Model\Export\Data\ImmediateProducts;
use Magento\CloudDocker\Cli;
use Magento\Framework\App\State;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\Area;

class SpecificProductExport extends Command
{
    /**
     * @var State
     */
    protected $state;

    /**
     * @var ExporterInterface
     */
    protected $exporter;

    /**
     * @var ImmediateProducts
     */
    protected $immediateProducts;

    /**
     * @inheritDoc
     *
     * @param State $state
     * @param ExporterInterface $exporter
     * @param ImmediateProducts $immediateProducts
     * @param string|null $name
     */
    public function __construct(
        State $state,
        ExporterInterface $exporter,
        ImmediateProducts $immediateProducts,
        string $name = null
    ) {
        $this->state = $state;
        $this->exporter = $exporter;
        $this->immediateProducts = $immediateProducts;
        parent::__construct($name);
    }

    /**
     * @inheritDoc
     */
    protected function configure()
    {
        $this->setName('fredhopper:indexer:immediate_export')
            ->setDescription('Run the Fredhopper export for specific products');

        $this->setHelp(<<<EOH
To bypass the indexing process and export specific products immediately
EOH
        );

        $desc = 'If true, zip file will be generated, but no upload to FH will be performed';
        $this->addOption('dry-run', null, null, $desc);

        $desc = 'Product SKU(s), e.g. ABC123.456';
        $this->addArgument('sku', InputArgument::REQUIRED | InputArgument::IS_ARRAY, $desc);
    }

    /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->exporter->setDryRun($input->getOption('dry-run'));

        $skus = $input->getArgument('sku');

        $this->immediateProducts->setSkus($skus);

        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Exception $ex) {
            // Just keep swimming
            ;
        }
        $this->exporter->export();
        return Cli::SUCCESS;
    }
}
