<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Plugin\Model\Indexer\Fulltext\Action;

use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;

class DataProviderPlugin
{
    /**
     * @param DataProvider $subject
     * @param $result
     * @param $indexData
     * @param $productData
     * @return array
     */
    public function afterPrepareProductIndex(
        DataProvider $subject,
        $result,
        $indexData,
        $productData
    ): array {
        // set product type id on the result
        $result['type_id'] = $productData['type_id'] ?? '';
        return $result;
    }
}
