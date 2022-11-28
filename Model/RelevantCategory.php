<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

class RelevantCategory
{

    private CategoryRepositoryInterface $categoryRepository;
    private CollectionFactory $categoryCollectionFactory;
    private GeneralConfig $config;
    private StoreManagerInterface $storeManager;

    private array $ancestorCategories;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CollectionFactory $categoryCollectionFactory,
        GeneralConfig $config,
        StoreManagerInterface $storeManager
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    /**
     * @return int[]
     */
    private function getAncestorCategoryIds(): array
    {
        if (isset($this->ancestorCategories)) {
            return $this->ancestorCategories;
        }

        $rootCategoryId = $this->config->getRootCategoryId();
        try {
            $rootCategory = $this->categoryRepository->get($rootCategoryId);
            $rootAncestors = explode('/', $rootCategory->getPath());

            // Root category itself is always allowed
            array_pop($rootAncestors);

            $this->ancestorCategories = array_filter($rootAncestors);
        } catch (\Exception $ex) {
            // Root category configured incorrectly?
            $this->ancestorCategories = [];
        }
        return $this->ancestorCategories;
    }

    /**
     * @param int|null $storeId
     * @return Collection
     */
    public function getCollection(?int $storeId = null): Collection
    {
        $ancestorIds = $this->getAncestorCategoryIds();

        $categoryIds = [];
        // loop through each store as is_active may be set at store level
        foreach ($this->storeManager->getStores() as $store) {
            // if store id is given, then skip other stores
            if ($storeId !== null && $storeId !== (int)$store->getId()) {
                continue;
            }
            if (in_array($store->getId(), $this->config->getExcludedStores())) {
                continue;
            }
            $categories = $this->categoryCollectionFactory->create();
            $categories->setStoreId((int)$store->getId());
            $categories->addAttributeToFilter(CategoryInterface::KEY_IS_ACTIVE, 1);
            if (count($ancestorIds) > 0) {
                $categories->addAttributeToFilter('entity_id', ['nin' => $ancestorIds]);
            }
            $categoryIds[] = $categories->getAllIds();
        }

        // ensure the root category is in the collection
        $categoryIds = array_merge([$this->config->getRootCategoryId()], ...$categoryIds);
        $categories = $this->categoryCollectionFactory->create();
        $categories->addIdFilter($categoryIds);
        $categories->addNameToResult();
        return $categories;
    }
}
