<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Indexer\Data\Product;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\Exception\NoSuchEntityException;

class GetSearchableProducts
{

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory
    ) {
    }

    /**
     * Get all visible and enabled products
     *
     * @throws NoSuchEntityException
     */
    public function execute(
        int $storeId,
        array $staticFields,
        array $productIds,
        int $lastProductId = 0,
        $batchSize = 100
    ): array {
        /** @var ProductCollection $collection */
        $collection = $this->productCollectionFactory->create();
        $collection->addStoreFilter($storeId);
        $collection->addFieldToFilter('status', Status::STATUS_ENABLED);
        $collection->addFieldToFilter(
            'visibility',
            [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH
                ]
            ]
        );
        $collection->removeAttributeToSelect();
        $collection->addAttributeToSelect($staticFields);
        $collection->addFieldToFilter('entity_id', ['gt' => $lastProductId]);
        if (!empty($productIds)) {
            $collection->addIdFilter($productIds);
        }
        $collection->setPageSize($batchSize);
        return $collection->getItems();
    }
}
