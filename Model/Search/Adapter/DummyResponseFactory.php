<?php
namespace Aligent\FredhopperIndexer\Model\Search\Adapter;

class DummyResponseFactory
{
    /**
     * @var \Magento\Framework\Api\Search\DocumentFactory
     */
    protected $documentFactory;
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Api\Search\DocumentFactory $documentFactory
    )
    {
        $this->documentFactory = $documentFactory;
        $this->objectManager = $objectManager;
    }

    public function create()
    {
        $aggregation = $this->objectManager->create(
            \Magento\Framework\Search\Response\Aggregation::class,
            ['buckets' => []]
        );
        return $this->objectManager->create(
            \Magento\Framework\Search\Response\QueryResponse::class,
            [
                'documents' => [],
                'aggregations' => $aggregation,
                'total' => 0
            ]
        );
    }
}
