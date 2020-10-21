<?php
namespace Aligent\FredhopperIndexer\Plugin\Model\Indexer\Fulltext\Action;

class DataProviderPlugin
{
    public function afterPrepareProductIndex(
        \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider $subject,
        $result,
        $indexData,
        $productData
    ) {
        // set product type id on the result
        $result['type_id'] = $productData['type_id'] ?? '';
        return $result;
    }
}
