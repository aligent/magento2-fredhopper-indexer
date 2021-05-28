<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Model\Export\Data\Meta;
use Magento\Catalog\Api\Data\CategoryInterface;

class CategoryFieldsProvider implements \Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface
{
    /**
     * @var \Magento\AdvancedSearch\Model\ResourceModel\Index
     */
    protected $index;

    /**
     * @var \Magento\Catalog\Api\CategoryRepositoryInterface
     */
    protected $categoryRepository;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var \Aligent\FredhopperIndexer\Helper\GeneralConfig
     */
    protected $config;

    /**
     * @var int
     */
    protected $rootCategoryId;

    /**
     * @var int[]
     */
    protected $excludeCategories;

    public function __construct(
        \Magento\AdvancedSearch\Model\ResourceModel\Index $index,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Aligent\FredhopperIndexer\Helper\GeneralConfig $config
    ) {
        $this->index = $index;
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId)
    {
        if (!isset($this->ancestorCategories)) {
            $this->rootCategoryId = $this->config->getRootCategoryId();
            $this->excludeCategories = [];
            try {
                $rootCategory = $this->categoryRepository->get($this->rootCategoryId);
                $rootAncestors = explode('/', $rootCategory->getPath());
                $this->excludeCategories = array_filter($rootAncestors);
            } catch (\Exception $ex) {
                // Root category configured incorrectly?
                ;
            }

            /**
             * @var \Magento\Catalog\Model\ResourceModel\Category\Collection $disabledCategories
             */
            $disabledCategories = $this->categoryCollectionFactory->create();
            $disabledCategories->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID);
            $disabledCategories->addAttributeToFilter(CategoryInterface::KEY_IS_ACTIVE, 0);
            foreach ($disabledCategories as $disabledCategory) {
                $this->excludeCategories[] = $disabledCategory->getId();
            }
        }

        $result = [];
        // gives array of form [product id][category_id] = position
        $productCategoryData = $this->index->getCategoryProductIndexData($storeId, $productIds);
        foreach ($productCategoryData as $productId => $categoryInfo) {
            $inRootCategory = isset($categoryInfo[$this->rootCategoryId]);

            // Remove unwanted categories (root category, ancestors, disabled categories)
            // as they won't be in FH and so each would generate a warning
            foreach ($this->excludeCategories as $excludeCategoryId) {
                unset($categoryInfo[$excludeCategoryId]);
            }

            // only care about category ids, not positions
            $result[$productId]['categories'] = array_keys($categoryInfo);

            // Re-add to root category using FH-specific ref
            if ($inRootCategory) {
                $result[$productId]['categories'][] = Meta::ROOT_CATEGORY_NAME;
            }
        }
        return $result;
    }
}
