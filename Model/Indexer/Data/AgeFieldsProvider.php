<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Helper\AgeAttributeConfig;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class AgeFieldsProvider implements AdditionalFieldsProviderInterface
{

    private CollectionFactory $productCollectionFactory;
    private AgeAttributeConfig $ageAttributeConfig;
    private int $currentTime;

    public function __construct(
        CollectionFactory $productCollectionFactory,
        AgeAttributeConfig $ageAttributeConfig
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->ageAttributeConfig = $ageAttributeConfig;
        $this->currentTime = time();
    }
    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId): array
    {
        if (!$this->ageAttributeConfig->getSendNewIndicator() && !$this->ageAttributeConfig->getSendDaysOnline()) {
            return [];
        }
        $createdAtFieldName = $this->ageAttributeConfig->getCreatedAtFieldName();
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addIdFilter($productIds);
        $productCollection->addStoreFilter($storeId);
        if ($this->ageAttributeConfig->getSendNewIndicator()) {
            $productCollection->addAttributeToSelect('news_from_date');
        }
        if ($this->ageAttributeConfig->getSendDaysOnline()) {
            $productCollection->addAttributeToSelect($createdAtFieldName);
        }
        $results = [];
        $products = $productCollection->getItems();
        foreach ($products as $productId => $product) {
            if ($this->ageAttributeConfig->getSendNewIndicator()) {
                // product is considered new as long as it has a news_from_date value
                $results[$productId]['is_new'] = (int)((bool)$product->getData('news_from_date')); // boolean as 1/0
            }
            if ($this->ageAttributeConfig->getSendDaysOnline()) {
                $createdTime = strtotime($product->getData($createdAtFieldName));
                // convert seconds to days (rounded down)
                $daysOnline = (int)(($this->currentTime - $createdTime) / (60 * 60 * 24));
                $results[$productId]['days_online'] = $daysOnline;
            }
        }
        return $results;
    }
}
