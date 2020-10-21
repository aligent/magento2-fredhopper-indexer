<?php
namespace Aligent\FredhopperIndexer\Model\Search;

use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Response\QueryResponse;

class DummySearchAdapter implements \Magento\Framework\Search\AdapterInterface
{
    /**
     * @var Adapter\DummyResponseFactory
     */
    protected $responseFactory;

    public function __construct(\Aligent\FredhopperIndexer\Model\Search\Adapter\DummyResponseFactory $responseFactory) {

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
