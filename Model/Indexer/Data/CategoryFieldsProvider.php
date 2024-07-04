<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Aligent\FredhopperIndexer\Model\Export\Data\Meta;
use Aligent\FredhopperIndexer\Model\RelevantCategory;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\AdvancedSearch\Model\ResourceModel\Index;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class CategoryFieldsProvider implements AdditionalFieldsProviderInterface
{

    /** @var int */
    private int $rootCategoryId;
    /** @var array<int, bool> */
    private array $allowedCategories;
    /** @var array<int, array<int>> */
    private array $storeCategories = [];

    /**
     * @param Index $index
     * @param RelevantCategory $relevantCategory
     * @param GeneralConfig $config
     */
    public function __construct(
        private readonly Index $index,
        private readonly RelevantCategory $relevantCategory,
        private readonly GeneralConfig $config
    ) {
    }

    /**
     * @inheritDoc
     * @throws NoSuchEntityException|LocalizedException
     */
    public function getFields(array $productIds, $storeId): array
    {
        // generate complete list of allowed categories based on the configured root category
        if (!isset($this->allowedCategories)) {
            $this->rootCategoryId = $this->config->getRootCategoryId();
            $collection = $this->relevantCategory->getCollection();
            $allowCategories = [];
            foreach ($collection as $category) {
                $allowCategories[$category->getId()] = true;
            }
            $this->allowedCategories = $allowCategories;
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
                if (isset($this->allowedCategories[$catId]) && in_array($catId, $this->storeCategories[$storeId])) {
                    $result[$productId]['categories'][] = $catId;
                }
            }
        }
        return $result;
    }
}
