<?php

namespace Aligent\FredhopperIndexer\Plugin\Model\Indexer\Fulltext\Action;

class FullPlugin
{
    /**
     * @var \Aligent\FredhopperIndexer\Model\Indexer\Data\FredhopperDataProvider
     */
    protected $fredhopperDataProvider;

    public function __construct(
        \Aligent\FredhopperIndexer\Model\Indexer\Data\FredhopperDataProvider $fredhopperDataProvider
    ) {
        $this->fredhopperDataProvider = $fredhopperDataProvider;
    }

    public function aroundRebuildStoreIndex(
        \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\Full $subject,
        callable $proceed,
        $storeId,
        $productIds = null
    ) {
        return $this->fredhopperDataProvider->rebuildStoreIndex($storeId, $productIds);
    }
}
