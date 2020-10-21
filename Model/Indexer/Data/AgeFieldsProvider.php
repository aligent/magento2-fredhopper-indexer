<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

class AgeFieldsProvider implements \Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface
{
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\AgeAttributeConfig
     */
    protected $ageAttributeConfig;
    /**
     * @var int
     */
    protected $currentTime;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Aligent\FredhopperIndexer\Helper\AgeAttributeConfig $ageAttributeConfig
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->ageAttributeConfig = $ageAttributeConfig;
        $this->currentTime = time();
    }
    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId)
    {
        if (!$this->ageAttributeConfig->getSendNewIndicator() && !$this->ageAttributeConfig->getSendDaysOnline()) {
            return [];
        }
        $productCollection = $this->productCollectionFactory->create();
        $productCollection->addIdFilter($productIds);
        $productCollection->addStoreFilter($storeId);
        if ($this->ageAttributeConfig->getSendNewIndicator()) {
            $productCollection->addAttributeToSelect('news_from_date');
        }
        $results = [];
        $products = $productCollection->getItems();
        foreach ($products as $productId => $product) {
            if ($this->ageAttributeConfig->getSendNewIndicator()) {
                // product is considered new as long as it has a news_from_date value
                $results[$productId]['is_new'] = (int)((bool)$product->getData('news_from_date')); // boolean as 1/0
            }
            if ($this->ageAttributeConfig->getSendDaysOnline()) {
                $createdTime = strtotime($product->getData('created_at'));
                // convert seconds to days (rounded down)
                $daysOnline = (int)(($this->currentTime - $createdTime) / (60 * 60 * 24));
                $results[$productId]['days_online'] = $daysOnline;
            }
        }
        return $results;
    }
}
