<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Magento\Store\Model\Store;

class RelevantCategory
{

    private CategoryRepositoryInterface $categoryRepository;
    private CollectionFactory $categoryCollectionFactory;
    private GeneralConfig $config;

    protected array $ancestorCategories;

    public function __construct(
        CategoryRepositoryInterface $categoryRepository,
        CollectionFactory $categoryCollectionFactory,
        GeneralConfig $config
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->config = $config;
    }

    /**
     * @return int[]
     */
    public function getAncestorCategoryIds(): array
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
     * @return Collection
     */
    public function getCollection(): Collection
    {
        $ancestorIds = $this->getAncestorCategoryIds();

        $categories = $this->categoryCollectionFactory->create();
        $categories->setStoreId(Store::DEFAULT_STORE_ID);
        $categories->addAttributeToFilter(CategoryInterface::KEY_IS_ACTIVE, 1);
        if (count($ancestorIds) > 0) {
            $categories->addAttributeToFilter('entity_id', ['nin' => $ancestorIds]);
        }
        $categories->addNameToResult();
        return $categories;
    }
}
