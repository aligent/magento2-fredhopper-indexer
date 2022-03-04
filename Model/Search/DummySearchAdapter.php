<?php
namespace Aligent\FredhopperIndexer\Model\Search;

use Aligent\FredhopperIndexer\Model\Search\Adapter\DummyResponseFactory;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\RequestInterface;

class DummySearchAdapter implements AdapterInterface
{
    /**
     * @var Adapter\DummyResponseFactory
     */
    protected $responseFactory;

    public function __construct(DummyResponseFactory $responseFactory)
    {

        $this->responseFactory = $responseFactory;
    }

    /**
     * @inheritDoc
     */
    public function query(RequestInterface $request)
    {
        return $this->responseFactory->create();
    }
}
