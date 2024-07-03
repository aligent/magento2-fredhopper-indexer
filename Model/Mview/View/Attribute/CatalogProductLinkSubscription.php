<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Mview\View\Attribute;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DB\Ddl\Trigger;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\TriggerFactory;
use Magento\Framework\EntityManager\EntityMetadataInterface;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Mview\View\ChangelogInterface;
use Magento\Framework\Mview\View\CollectionInterface;
use Magento\Framework\Mview\ViewInterface;
use Magento\Framework\Mview\Config;
use Magento\Framework\Mview\View\Subscription;

/**
 * Subscription model class for "catalog_product_link" table
 */
class CatalogProductLinkSubscription extends Subscription
{
    private const CATALOG_PRODUCT_LINK_PRODUCT_ID = 'product_id';

    /**
     * @var EntityMetadataInterface
     */
    private EntityMetadataInterface $entityMetadata;

    /**
     * Save state of Subscription for build statement for retrieving entity id value
     *
     * @var array
     */
    private array $statementState = [];

    /**
     * @param ResourceConnection $resource
     * @param TriggerFactory $triggerFactory
     * @param CollectionInterface $viewCollection
     * @param ViewInterface $view
     * @param string $tableName
     * @param string $columnName
     * @param MetadataPool $metadataPool
     * @param array $ignoredUpdateColumns
     * @param array $ignoredUpdateColumnsBySubscription
     * @param Config|null $mviewConfig
     * @throws \Exception
     */
    public function __construct(
        ResourceConnection $resource,
        TriggerFactory $triggerFactory,
        CollectionInterface $viewCollection,
        ViewInterface $view,
        $tableName,
        $columnName,
        private readonly MetadataPool $metadataPool,
        private readonly array $ignoredUpdateColumns = [],
        private readonly array $ignoredUpdateColumnsBySubscription = [],
        Config $mviewConfig = null
    ) {
        parent::__construct(
            $resource,
            $triggerFactory,
            $viewCollection,
            $view,
            $tableName,
            $columnName,
            $ignoredUpdateColumns,
            $ignoredUpdateColumnsBySubscription,
            $mviewConfig
        );
    }

    /**
     * Build trigger statement for INSERT, UPDATE, DELETE events
     *
     * @param string $event
     * @param ViewInterface $view
     * @return string
     * @throws \Exception
     */
    protected function buildStatement(string $event, ViewInterface $view): string
    {
        $triggerBody = '';

        switch ($event) {
            case Trigger::EVENT_INSERT:
            case Trigger::EVENT_UPDATE:
                $eventType = 'NEW';
                break;
            case Trigger::EVENT_DELETE:
                $eventType = 'OLD';
                break;
            default:
                return $triggerBody;
        }
        $changelog = $view->getChangelog();
        $entityIdHash = $this->getEntityMetadata()->getIdentifierField()
            . $this->getEntityMetadata()->getEntityTable()
            . $this->getEntityMetadata()->getLinkField()
            . $event;
        if (!isset($this->statementState[$entityIdHash])) {
            $triggerBody = $this->buildEntityIdStatementByEventType($eventType);
            $this->statementState[$entityIdHash] = true;
        }

        $trigger = $this->buildStatementByEventType($view, $event);
        if ($event == Trigger::EVENT_UPDATE) {
            $trigger = $this->addConditionsToTrigger($trigger, $changelog);
        }
        $triggerBody .= $trigger;

        return $triggerBody;
    }

    /**
     * Adds quoted conditions to the trigger
     *
     * @param string $trigger
     * @param ChangelogInterface $changelog
     * @return string
     */
    private function addConditionsToTrigger(string $trigger, ChangelogInterface $changelog): string
    {
        $tableName = $this->resource->getTableName($this->getTableName());
        if ($this->connection->isTableExists($tableName)
            && $describe = $this->connection->describeTable($tableName)
        ) {
            $columnNames = array_column($describe, 'COLUMN_NAME');
            $ignoredColumnsBySubscription = array_filter(
                $this->ignoredUpdateColumnsBySubscription[$changelog->getViewId()][$this->getTableName()] ?? []
            );
            $ignoredColumns = array_merge(
                $this->ignoredUpdateColumns,
                array_keys($ignoredColumnsBySubscription)
            );
            $columnNames = array_diff($columnNames, $ignoredColumns);
            if ($columnNames) {
                $columns = [];
                foreach ($columnNames as $columnName) {
                    $columns[] = sprintf(
                        'NOT(NEW.%1$s <=> OLD.%1$s)',
                        $this->connection->quoteIdentifier($columnName)
                    );
                }
                $trigger = sprintf(
                    "IF (%s) THEN %s END IF;",
                    implode(' OR ', $columns),
                    $trigger
                );
            }
        }

        return $trigger;
    }

    /**
     * Build trigger body
     *
     * @param string $eventType
     * @return string
     * @throws \Exception
     */
    private function buildEntityIdStatementByEventType(string $eventType): string
    {
        return vsprintf(
                'SET @entity_id = (SELECT %1$s FROM %2$s WHERE %3$s = %4$s.%5$s);',
                [
                    $this->connection->quoteIdentifier(
                        $this->getEntityMetadata()->getIdentifierField()
                    ),
                    $this->connection->quoteIdentifier(
                        $this->resource->getTableName($this->getEntityMetadata()->getEntityTable())
                    ),
                    $this->connection->quoteIdentifier(
                        $this->getEntityMetadata()->getLinkField()
                    ),
                    $eventType,
                    self::CATALOG_PRODUCT_LINK_PRODUCT_ID
                ]
            ) . PHP_EOL;
    }

    /**
     * Override column name as we need to use entity_id instead of link field
     *
     * @param string $prefix
     * @param ViewInterface $view
     * @return string
     */
    public function getEntityColumn(string $prefix, ViewInterface $view): string
    {
        return '@entity_id';
    }

    /**
     * Build sql statement for trigger
     *
     * @param ViewInterface $view
     * @param string $event
     * @return string
     * @throws \Exception
     */
    private function buildStatementByEventType(ViewInterface $view, string $event): string
    {
        $columns = $this->prepareColumns($view, $event);
        return vsprintf(
            'INSERT IGNORE INTO %s (%s) values(%s);',
            [
                $this->connection->quoteIdentifier(
                    $this->resource->getTableName($view->getChangelog()->getName())
                ),
                implode(', ', $columns['column_names']),
                implode(', ', $columns['column_values'])
            ]
        );
    }

    /**
     * Get Catalog Product metadata
     *
     * @throws \Exception
     */
    private function getEntityMetaData(): EntityMetadataInterface
    {
        if (!isset($this->entityMetadata)) {
            $this->entityMetadata = $this->metadataPool->getMetadata(ProductInterface::class);
        }
        return $this->entityMetadata;
    }
}
