<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Plugin\Model\Indexer\Fulltext\Action;

use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;

class DataProviderPlugin
{
    public function afterPrepareProductIndex(
        DataProvider $subject,
        $result,
        $indexData,
        $productData
    ) {
        // set product type id on the result
        $result['type_id'] = $productData['type_id'] ?? '';
        return $result;
    }
}
