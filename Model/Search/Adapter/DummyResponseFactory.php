<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Search\Adapter;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Search\Response\Aggregation;
use Magento\Framework\Search\Response\QueryResponse;

class DummyResponseFactory
{

    private ObjectManagerInterface $objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
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
