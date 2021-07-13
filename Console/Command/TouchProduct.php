<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TouchProduct extends \Symfony\Component\Console\Command\Command
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;

    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
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

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $skus = $input->getArgument('sku');
        if (count($skus) < 1) {
            throw new \InvalidArgumentException('Must provide SKUs');
        }

        /**
         * @var \Magento\Framework\DB\Adapter\AdapterInterface $conn
         */
        $conn = $this->resourceConnection->getConnection();

        $table = $conn->getTableName('catalog_product_entity');
        $fields = ['updated_at' => new \Zend_Db_Expr('NOW()')];
        $where = ["sku IN (?)" => $skus];
        $affected = $conn->update($table, $fields, $where);
        $output->writeln("Affected {$affected} SKU(s)");
    }
}
