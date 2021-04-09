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
     * @var \Magento\Catalog\Api\CategoryRepositoryInterface
     */
    protected $categoryRepository;

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
    protected $ancestorCategories;

    public function __construct(
        \Magento\AdvancedSearch\Model\ResourceModel\Index $index,
        \Magento\Catalog\Api\CategoryRepositoryInterface $categoryRepository,
        \Aligent\FredhopperIndexer\Helper\GeneralConfig $config
    ) {
        $this->index = $index;
        $this->categoryRepository = $categoryRepository;
        $this->config = $config;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId)
    {
        if (!isset($this->ancestorCategories)) {
            $this->rootCategoryId = $this->config->getRootCategoryId();
            $this->ancestorCategories = [];
            try {
                $rootCategory = $this->categoryRepository->get($this->rootCategoryId);
                $rootAncestors = explode('/', $rootCategory->getPath());
                $this->ancestorCategories = array_filter($rootAncestors);
            } catch (\Exception $ex) {
                // Root category configured incorrectly?
                ;
            }
        }

        $result = [];
        // gives array of form [product id][category_id] = position
        $productCategoryData = $this->index->getCategoryProductIndexData($storeId, $productIds);
        foreach ($productCategoryData as $productId => $categoryInfo) {
            $inRootCategory = isset($categoryInfo[$this->rootCategoryId]);

            // Remove unwanted categories (root category and ancestors)
            // as they won't be in FH and so each would generate a warning
            foreach ($this->ancestorCategories as $ancestorId) {
                unset($categoryInfo[$ancestorId]);
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
