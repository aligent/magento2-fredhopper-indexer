<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

class CategoryFieldsProvider implements \Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface
{
    /**
     * @var \Magento\AdvancedSearch\Model\ResourceModel\Index
     */
    protected $index;

    public function __construct(
        \Magento\AdvancedSearch\Model\ResourceModel\Index $index
    ) {
        $this->index = $index;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId)
    {
        $result = [];
        // gives array of form [product id][category_id] = position
        $productCategoryData = $this->index->getCategoryProductIndexData($storeId, $productIds);
        foreach ($productCategoryData as $productId => $categoryInfo) {
            // only care about category ids, not positions
            $result[$productId]['categories'] = array_keys($categoryInfo);
        }
        return $result;
    }
}
