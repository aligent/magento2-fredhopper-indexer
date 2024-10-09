<?php

declare(strict_types=1);

namespace Aligent\FredhopperCommon\Model;

use Aligent\FredhopperCommon\Model\Config\GeneralConfig;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;

class RelevantCategory
{
    /**
     * @var array
     */
    private array $ancestorCategories;

    /**
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CollectionFactory $categoryCollectionFactory
     * @param GeneralConfig $config
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly GeneralConfig $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Get a collection of all relevant categories
     *
     * @param int|null $storeId
     * @return Collection
     * @throws LocalizedException
     */
    public function getCollection(?int $storeId = null): Collection
    {
        $ancestorIds = $this->getAncestorCategoryIds();

        $categoryIds = [];
        $rootCategories = [$this->config->getRootCategoryId()];
        // loop through each store as is_active may be set at store level
        foreach ($this->storeManager->getStores() as $store) {
            // if store id is given, then skip other stores
            if ($storeId !== null && $storeId !== (int)$store->getId()) {
                continue;
            }
            if (in_array($store->getId(), $this->config->getExcludedStores())) {
                continue;
            }
            $storeGroupId = $store->getStoreGroupId();
            $rootCategoryForStore = $this->storeManager->getGroup($storeGroupId)->getRootCategoryId();
            $rootCategories[] = $rootCategoryForStore;

            /** @var CategoryCollection $categories */
            $categories = $this->categoryCollectionFactory->create();
            $categories->setStoreId((int)$store->getId());
            $regExpPathFilter = sprintf('.*/%s/[/0-9]*$', $rootCategoryForStore);
            $categories->addPathFilter($regExpPathFilter);
            $categories->addAttributeToFilter(CategoryInterface::KEY_IS_ACTIVE, 1);
            if (count($ancestorIds) > 0) {
                $categories->addAttributeToFilter('entity_id', ['nin' => $ancestorIds]);
            }
            $categoryIds[] = $categories->getAllIds();
        }

        // ensure the root category is in the collection
        $categoryIds = array_merge($rootCategories, ...$categoryIds);
        $categories = $this->categoryCollectionFactory->create();
        $categories->addIdFilter($categoryIds);
        $categories->addNameToResult();
        return $categories;
    }

    /**
     * Get all ancestor category ids
     *
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
        } catch (\Exception) {
            // Root category configured incorrectly?
            $this->ancestorCategories = [];
        }
        return $this->ancestorCategories;
    }
}
