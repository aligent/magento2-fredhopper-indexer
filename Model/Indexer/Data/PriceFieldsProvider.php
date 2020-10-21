<?php


namespace Aligent\FredhopperIndexer\Model\Indexer\Data;


use Magento\Store\Model\Indexer\WebsiteDimensionProvider;

class PriceFieldsProvider implements \Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface
{
    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceConnection;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var \Aligent\FredhopperIndexer\Helper\PricingAttributeConfig
     */
    protected $pricingAttributeConfig;
    /**
     * @var \Magento\Catalog\Model\Indexer\Product\Price\DimensionCollectionFactory
     */
    protected $dimensionCollectionFactory;
    /**
     * @var \Magento\Framework\Search\Request\IndexScopeResolverInterface
     */
    protected $tableResolver;


    public function __construct(
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Aligent\FredhopperIndexer\Helper\PricingAttributeConfig $pricingAttributeConfig,
        \Magento\Catalog\Model\Indexer\Product\Price\DimensionCollectionFactory $dimensionCollectionFactory,
        \Magento\Framework\Search\Request\IndexScopeResolverInterface $tableResolver
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->pricingAttributeConfig = $pricingAttributeConfig;
        $this->dimensionCollectionFactory = $dimensionCollectionFactory;
        $this->tableResolver = $tableResolver;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId)
    {
        $result = [];
        $useCustomerGroup = $this->pricingAttributeConfig->getUseCustomerGroup();
        $useRange = $this->pricingAttributeConfig->getUseRange();

        $indexFields = [
            'price' => 'regular_price',
            'final_price' => 'special_price'
        ];
        if ($useRange) {
            $indexFields = array_merge($indexFields, [
                'min_price' => 'min_price',
                'max_price' => 'max_price'
            ]);
        }

        $productPriceData = $this->getPriceIndexData($productIds, $storeId, $indexFields);
        foreach ($productPriceData as $productId => $customerGroupPriceData) {
            $result[$productId] = [];
            foreach ($customerGroupPriceData as $customerGroupId => $prices) {
                foreach ($indexFields as $fieldName) {
                    $attributeName = $fieldName . ($useCustomerGroup ? "_{$customerGroupId}" : "");
                    $result[$productId][$attributeName] = $prices[$fieldName];
                }
            }
        }
        return $result;
    }

    protected function getPriceIndexData(array $productIds, $storeId, $indexFields)
    {
        $connection = $this->resourceConnection->getConnection();
        $websiteId = $this->storeManager->getStore($storeId)->getWebsiteId();

        $selects = [];
        foreach ($this->dimensionCollectionFactory->create() as $dimensions) {
            if (!isset($dimensions[WebsiteDimensionProvider::DIMENSION_NAME]) ||
                $websiteId === null ||
                $dimensions[WebsiteDimensionProvider::DIMENSION_NAME] === $websiteId) {
                $select = $connection->select()->from(
                    $this->tableResolver->resolve('catalog_product_index_price', $dimensions),
                    ['entity_id', 'customer_group_id', 'website_id', 'price', 'final_price', 'min_price', 'max_price']
                );
                if ($productIds) {
                    $select->where('entity_id IN (?)', $productIds);
                }
                $selects[] = $select;
            }
        }

        $unionSelect = $connection->select()->union($selects);
        $result = [];
        foreach ($connection->fetchAll($unionSelect) as $row) {
            $prices = [];
            foreach ($indexFields as $columnName => $fieldName) {
                $prices[$fieldName] = round($row[$columnName], 2);
            }
            $result[$row['website_id']][$row['entity_id']][$row['customer_group_id']] = $prices;
        }

        return $result[$websiteId];
    }
}
