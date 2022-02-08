<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Model\Export\Data\Meta;

class CategoryFieldsProvider implements \Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface
{
    /**
     * @var \Magento\AdvancedSearch\Model\ResourceModel\Index
     */
    protected $index;

    /**
     * @var \Aligent\FredhopperIndexer\Model\RelevantCategory
     */
    protected $relevantCategory;

    /**
     * @var \Aligent\FredhopperIndexer\Helper\GeneralConfig
     */
    protected $config;

    /**
     * @var int
     */
    protected $rootCategoryId;

    /**
     * Array has form [int:category id => bool:allowed?]
     * @var array|null
     */
    protected $allowCategories = null;

    public function __construct(
        \Magento\AdvancedSearch\Model\ResourceModel\Index $index,
        \Aligent\FredhopperIndexer\Model\RelevantCategory $relevantCategory,
        \Aligent\FredhopperIndexer\Helper\GeneralConfig $config
    ) {
        $this->index = $index;
        $this->relevantCategory = $relevantCategory;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId)
    {
        if (!isset($this->allowCategories)) {
            $this->rootCategoryId = (int)$this->config->getRootCategoryId();
            $collection = $this->relevantCategory->getCollection();
            $allowCategories = [];
            foreach ($collection as $category) {
                $allowCategories[$category->getId()] = true;
            }
            $this->allowCategories = $allowCategories;
        }

        $result = [];
        // gives array of form [product id][category_id] = position
        $productCategoryData = $this->index->getCategoryProductIndexData($storeId, $productIds);
        foreach ($productCategoryData as $productId => $categoryInfo) {
            $result[$productId]['categories'] = [];

            // Add to root category
            if (isset($categoryInfo[$this->rootCategoryId])) {
                $result[$productId]['categories'][] = Meta::ROOT_CATEGORY_NAME;
                unset($categoryInfo[$this->rootCategoryId]);
            }

            // Add to other relevant categories
            foreach ($categoryInfo as $catId => $position) {
                if (isset($this->allowCategories[$catId])) {
                    $result[$productId]['categories'][] = $catId;
                }
            }
        }
        return $result;
    }
}
