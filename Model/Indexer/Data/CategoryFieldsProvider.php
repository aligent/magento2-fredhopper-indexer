<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Aligent\FredhopperIndexer\Model\Export\Data\Meta;
use Aligent\FredhopperIndexer\Model\RelevantCategory;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\AdvancedSearch\Model\ResourceModel\Index;
use Magento\Framework\Exception\NoSuchEntityException;

class CategoryFieldsProvider implements AdditionalFieldsProviderInterface
{

    private Index $index;
    private RelevantCategory $relevantCategory;
    private GeneralConfig $config;

    private int $rootCategoryId;
    /**
     * Array has form [int:category id => bool:allowed?]
     */
    private array $allowCategories;
    private array $storeCategories = [];

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
     * @throws NoSuchEntityException
     */
    public function getFields(array $productIds, $storeId): array
    {
        // generate complete list of allowed categories based on the configured root category
        if (!isset($this->allowCategories)) {
            $this->rootCategoryId = $this->config->getRootCategoryId();
            $collection = $this->relevantCategory->getCollection();
            $allowCategories = [];
            foreach ($collection as $category) {
                $allowCategories[$category->getId()] = true;
            }
            $this->allowCategories = $allowCategories;
        }

        // get list of store categories
        if (!isset($this->storeCategories[$storeId])) {
            $collection = $this->relevantCategory->getCollection($storeId);
            $this->storeCategories[$storeId] = $collection->getAllIds();
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

            // Add to other relevant categories, as long as the category is valid for the current store
            foreach ($categoryInfo as $catId => $position) {
                if (isset($this->allowCategories[$catId]) && in_array($catId, $this->storeCategories[$storeId])) {
                    $result[$productId]['categories'][] = $catId;
                }
            }
        }
        return $result;
    }
}
