<?php
namespace Aligent\FredhopperIndexer\Model\Search\Adapter;

use Magento\Framework\Api\Search\DocumentFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\QueryResponse;

class DummyResponseFactory
{
    /**
     * @var DocumentFactory
     */
    protected $documentFactory;
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        DocumentFactory $documentFactory
    ) {
        $this->documentFactory = $documentFactory;
        $this->objectManager = $objectManager;
    }

    public function create()
    {
        $aggregation = $this->objectManager->create(
            Aggregation::class,
            ['buckets' => []]
        );
        return $this->objectManager->create(
            QueryResponse::class,
            [
                'documents' => [],
                'aggregations' => $aggregation,
                'total' => 0
            ]
        );
    }
}
