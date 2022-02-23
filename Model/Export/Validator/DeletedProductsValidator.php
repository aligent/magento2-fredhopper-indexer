<?php
namespace Aligent\FredhopperIndexer\Model\Export\Validator;

use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Validation\ValidationException;

class DeletedProductsValidator implements \Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface
{
    protected const DELETED_PRODUCTS_THRESHOLD = 10;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    public function __construct(ResourceConnection $resourceConnection)
    {
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritDoc
     */
    public function validateState()
    {
        // check the number of deleted products does not reach the threshold
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(DataHandler::INDEX_TABLE_NAME)
            ->reset(Select::COLUMNS)
            ->columns('count(1)')
            ->where('operation_type = ?', DataHandler::OPERATION_TYPE_DELETE);
        $result = $connection->query($select);
        $productCount = $result->fetchColumn();

        if ($productCount > self::DELETED_PRODUCTS_THRESHOLD) {
            throw new ValidationException(__(
                'Number of deleted products (%1) exceeds threshold (%2) - aborting',
                $productCount,
                self::DELETED_PRODUCTS_THRESHOLD)
            );
        }
    }
}
