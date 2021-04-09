<?php
namespace Aligent\FredhopperIndexer\Model\Config\Source;

use Magento\Framework\Data\Collection;

class Category implements \Magento\Framework\Data\OptionSourceInterface
{
    const MAX_DEPTH = 3;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var Category\NodeFactory
     */
    private $nodeFactory;

    /**
     * @var Category\Node
     */
    private $categories;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Aligent\FredhopperIndexer\Model\Config\Source\Category\NodeFactory $nodeFactory
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->nodeFactory = $nodeFactory;
    }

    protected function loadCategories() {
        /**
         * @var \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection
         */
        $categoryCollection = $this->categoryCollectionFactory->create();
        $categoryCollection->setStoreId(0);
        $categoryCollection->addNameToResult();
        $categoryCollection->addOrder('parent_id', Collection::SORT_ORDER_ASC);
        $categoryCollection->addOrder('position', Collection::SORT_ORDER_ASC);
        $categoryCollection->addFieldToFilter('level', ['lteq' => static::MAX_DEPTH]);

        $this->categories = $this->nodeFactory->create([
            'id' => 0,
            'name' => 'Base category',
        ]);

        // Initial pass: load categories that can be loaded in order from DB
        $parents = [];
        $orphans = [];
        foreach ($categoryCollection as $category) {
            $parentId = (int) $category->getParentId();

            /**
             * @var Category\Node $node
             */
            $node = $this->nodeFactory->create([
                'id' => (int) $category->getEntityId(),
                'name' => $category->getName(),
            ]);
            $parent = null;
            if (isset($parents[$parentId])) {
                $parent = $parents[$parentId];
            } else {
                $parent = $this->categories->findById($parentId);
                if ($parent) {
                    $parents[$parentId] = $parent;
                }
            }
            if ($parent) {
                $parent->children[] = $node;
            } else {
                $orphans[$parentId][] = $node;
            }
        }

        // Secondary pass: attempt to add any orphaned nodes (probably just have out-of-order parent IDs)
        $tryAgain = true;
        while (count($orphans) > 0 && $tryAgain) {
            $tryAgain = false;
            foreach ($orphans as $parentId => $nodes) {
                if (!isset($parents[$parentId])) {
                    continue;
                }
                $tryAgain = true;
                $parent = $parents[$parentId];
                foreach ($nodes as $node) {
                    $parent->children[] = $node;
                    $parents[$node->id] = $node;
                }
                unset($orphans[$parentId]);
            }
        }

        // Append actual orphans (parent category doesn't exist)
        if (count($orphans) > 0) {
            /**
             * @var Category\Node $orphanParent
             */
            $orphanParent = $this->nodeFactory->create([
                'id' => -1,
                'name' => '* Orphans *',
            ]);
            foreach ($orphans as $parentId => $nodes) {
                /**
                 * @var Category\Node $node
                 */
                foreach ($nodes as $node) {
                    $node->name .= " (parent {$parentId})";
                    $orphanParent->children[] = $node;
                }
            }
        }
    }

    /**
     * @param array $options
     * @param Category\Node $category
     * @param int $indentLevel
     */
    public function addOptions(array &$options, Category\Node $category, int $indentLevel) {
        $label = $category->name;
        if ($indentLevel > 0) {
            $label = str_repeat('&nbsp;', $indentLevel * 4) . $label;
        }
        $options[] = ['value' => $category->id, 'label' => $label];
        foreach ($category->children as $child) {
            $this->addOptions($options, $child, $indentLevel + 1);
        }
    }

    public function toOptionArray()
    {
        if (empty($this->categories)) {
            $this->loadCategories();
        }

        $options = [];
        /**
         * @var Category\Node $category
         */
        foreach ($this->categories->children as $category) {
            $this->addOptions($options, $category, 0);
        }

        return $options;
    }
}
