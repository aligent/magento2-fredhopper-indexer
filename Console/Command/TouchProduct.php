<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TouchProduct extends Command
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    public function __construct(
        ResourceConnection $resourceConnection,
        string $name = null
    ) {
        $this->resourceConnection = $resourceConnection;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('fredhopper:indexer:touch_product')
            ->setDescription('Trigger reindex of one or more products');

        $this->setHelp(<<<EOH
Causes the specified products to be included in the next index and incremental export
EOH
        );

        $desc = 'SKU(s), e.g. ABC123';
        $this->addArgument('sku', InputArgument::REQUIRED | InputArgument::IS_ARRAY, $desc);
    }

    /**
     * @inheritDoc
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $skus = $input->getArgument('sku');
        if (count($skus) < 1) {
            throw new \InvalidArgumentException('Must provide SKUs');
        }

        $conn = $this->resourceConnection->getConnection();
        $productFetch = $conn->select()
            ->from($conn->getTableName('catalog_product_entity'))
            ->reset(\Zend_Db_Select::COLUMNS)
            ->columns(['entity_id'])
            ->where("sku IN (?)", $skus);
        $insertData = [];
        foreach ($conn->query($productFetch) as $row) {
            $insertData[] = ['entity_id' => $row['entity_id']];
        }

        if (count($insertData) == 0) {
            $output->writeln("No matching products");
            return Cli::RETURN_FAILURE;
        }

        $table = $conn->getTableName('catalogsearch_fulltext_cl');
        try {
            $conn->insertMultiple($table, $insertData);
            $output->writeln("Updated " . count($insertData) . " product(s)");
            return Cli::RETURN_SUCCESS;
        } catch (\Exception $ex) {
            $output->writeln("Failed to update changelog");
            return Cli::RETURN_FAILURE;
        }
    }
}
