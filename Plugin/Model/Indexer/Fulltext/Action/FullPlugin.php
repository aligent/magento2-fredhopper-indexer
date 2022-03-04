<?php

namespace Aligent\FredhopperIndexer\Plugin\Model\Indexer\Fulltext\Action;

use Aligent\FredhopperIndexer\Model\Indexer\Data\FredhopperDataProvider;
use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\Full;

class FullPlugin
{
    /**
     * @var FredhopperDataProvider
     */
    protected $fredhopperDataProvider;

    public function __construct(
        FredhopperDataProvider $fredhopperDataProvider
    ) {
        $this->fredhopperDataProvider = $fredhopperDataProvider;
    }

    /**
     * @param Full $subject
     * @param callable $proceed
     * @param $storeId
     * @param $productIds
     * @return \Generator
     */
    public function aroundRebuildStoreIndex(
        Full $subject,
        callable $proceed,
        $storeId,
        $productIds = null
    ): \Generator {
        return $this->fredhopperDataProvider->rebuildStoreIndex($storeId, $productIds);
    }
}
