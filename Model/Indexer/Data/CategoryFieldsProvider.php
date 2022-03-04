<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Aligent\FredhopperIndexer\Model\Export\Data\Meta;
use Aligent\FredhopperIndexer\Model\RelevantCategory;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\AdvancedSearch\Model\ResourceModel\Index;

class CategoryFieldsProvider implements AdditionalFieldsProviderInterface
{
    /**
     * @var Index
     */
    protected $index;

    /**
     * @var RelevantCategory
     */
    protected $relevantCategory;

    /**
     * @var GeneralConfig
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
        Index $index,
        RelevantCategory $relevantCategory,
        GeneralConfig $config
    ) {
        $this->index = $index;
        $this->relevantCategory = $relevantCategory;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId): array
    {
        if (!isset($this->allowCategories)) {
            $this->rootCategoryId = $this->config->getRootCategoryId();
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
