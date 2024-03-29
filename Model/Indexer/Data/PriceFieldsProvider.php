<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Aligent\FredhopperIndexer\Helper\PricingAttributeConfig;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Model\Indexer\Product\Price\DimensionCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Search\Request\IndexScopeResolverInterface;
use Magento\Store\Model\Indexer\WebsiteDimensionProvider;
use Magento\Store\Model\StoreManagerInterface;

class PriceFieldsProvider implements AdditionalFieldsProviderInterface
{

    private ResourceConnection $resourceConnection;
    private StoreManagerInterface $storeManager;
    private PricingAttributeConfig $pricingAttributeConfig;
    private DimensionCollectionFactory $dimensionCollectionFactory;
    private IndexScopeResolverInterface $tableResolver;

    public function __construct(
        ResourceConnection $resourceConnection,
        StoreManagerInterface $storeManager,
        PricingAttributeConfig $pricingAttributeConfig,
        DimensionCollectionFactory $dimensionCollectionFactory,
        IndexScopeResolverInterface $tableResolver
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->storeManager = $storeManager;
        $this->pricingAttributeConfig = $pricingAttributeConfig;
        $this->dimensionCollectionFactory = $dimensionCollectionFactory;
        $this->tableResolver = $tableResolver;
    }

    /**
     * @inheritDoc
     * @throws NoSuchEntityException
     * @throws \Zend_Db_Select_Exception
     */
    public function getFields(array $productIds, $storeId): array
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
                    $attributeName = $fieldName . ($useCustomerGroup ? "_$customerGroupId" : "");
                    $result[$productId][$attributeName] = $prices[$fieldName];
                }
            }
        }
        return $result;
    }

    /**
     * @param array $productIds
     * @param $storeId
     * @param $indexFields
     * @return array
     * @throws NoSuchEntityException
     * @throws \Zend_Db_Select_Exception
     */
    private function getPriceIndexData(array $productIds, $storeId, $indexFields): array
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
                $prices[$fieldName] = round((float)$row[$columnName], 2);
            }
            $result[$row['website_id']][$row['entity_id']][$row['customer_group_id']] = $prices;
        }

        return $result[$websiteId] ?? [];
    }
}
