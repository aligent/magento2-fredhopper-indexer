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
            ->columns('count(1)')
            ->where('operation_type = ?', DataHandler::OPERATION_TYPE_DELETE);
        $result = $connection->query($select);
        $productCount = $result->fetchColumn();

        $maxDeletes = $this->sanityCheckConfig->getMaxDeleteProducts();
        if ($productCount > $maxDeletes) {
            throw new ValidationException(
                __(
                    'Number of deleted products (%1) exceeds threshold (%2)',
                    $productCount,
                    $maxDeletes
                )
            );
        }
    }
}
