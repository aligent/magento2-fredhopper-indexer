<?php
namespace Aligent\FredhopperIndexer\Model\Export\Validator;

use Aligent\FredhopperIndexer\Api\Export\PreExportValidatorInterface;
use Aligent\FredhopperIndexer\Helper\SanityCheckConfig;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Validation\ValidationException;
use Zend_Db_Select;

class DeletedProductsValidator implements PreExportValidatorInterface
{
    /**
     * @var SanityCheckConfig
     */
    protected $sanityCheckConfig;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    public function __construct(
        SanityCheckConfig $sanityCheckConfig,
        ResourceConnection $resourceConnection
    ) {
        $this->sanityCheckConfig = $sanityCheckConfig;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritDoc
     * @throws \Zend_Db_Statement_Exception
     */
    public function validateState()
    {
        // check the number of deleted products does not reach the threshold
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(DataHandler::INDEX_TABLE_NAME)
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns(['store_id', 'product_count' => 'count(1)'])
            ->where('product_type = ?', DataHandler::TYPE_PRODUCT)
            ->where('operation_type = ?', DataHandler::OPERATION_TYPE_DELETE)
            ->group(['store_id'])
            ->order(['product_count DESC', 'store_id'])
            ->limit(1);
        $result = $connection->query($select);
        $row = $result->fetch();

        $maxDeletes = $this->sanityCheckConfig->getMaxDeleteProducts();
        if ($row['product_count'] > $maxDeletes) {
            throw new ValidationException(
                __(
                    'Number of deleted products (%1) in store %2 exceeds threshold (%3)',
                    $row['product_count'],
                    $row['store_id'],
                    $maxDeletes
                )
            );
        }
    }
}
