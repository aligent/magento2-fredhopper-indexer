<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Helper\AgeAttributeConfig;
use Aligent\FredhopperIndexer\Helper\AttributeConfig;
use Aligent\FredhopperIndexer\Helper\CustomAttributeConfig;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandler;
use Aligent\FredhopperIndexer\Model\Indexer\DataHandlerFactory;
use Aligent\FredhopperIndexer\Helper\GeneralConfig;
use Aligent\FredhopperIndexer\Helper\ImageAttributeConfig;
use Aligent\FredhopperIndexer\Helper\PricingAttributeConfig;
use Aligent\FredhopperIndexer\Helper\StockAttributeConfig;
use Magento\Catalog\Model\ResourceModel\Product;
use Magento\Store\Model\StoreDimensionProvider;
use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\Full;
use Magento\CatalogSearch\Model\ResourceModel\EngineProvider;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * Collates data for an immediate export without relying on the FH index tables
 */
class ImmediateProducts extends Products
{
    /**
     * @var array
     */
    protected $skus = [];

    /**
     * @var StoreDimensionProvider
     */
    protected $dimensionProvider;

    /**
     * @var Full
     */
    protected $indexerAction;

    /**
     * @var EngineProvider
     */
    protected $engineProvider;

    /**
     * @var DataHandlerFactory
     */
    protected $dataHandlerFactory;

    /**
     * @var Product
     */
    protected $productResource;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @param StoreDimensionProvider $dimensionProvider
     * @param Full $indexerAction
     * @param EngineProvider $engineProvider
     * @param Product $productResource
     * @param DataHandlerFactory $dataHandlerFactory
     * @param GeneralConfig $generalConfig
     * @param AttributeConfig $attributeConfig
     * @param PricingAttributeConfig $pricingAttributeConfig
     * @param StockAttributeConfig $stockAttributeConfig
     * @param AgeAttributeConfig $ageAttributeConfig
     * @param ImageAttributeConfig $imageAttributeConfig
     * @param CustomAttributeConfig $customAttributeConfig
     * @param Json $json
     * @param ResourceConnection $resource
     * @param array $siteVariantPriceAttributes
     * @param array $siteVariantStockAttributes
     * @param array $siteVariantImageAttributes
     * @param array $siteVariantAgeAttributes
     */
    public function __construct(
        StoreDimensionProvider $dimensionProvider,
        Full $indexerAction,
        EngineProvider $engineProvider,
        Product $productResource,
        DataHandlerFactory $dataHandlerFactory,
        GeneralConfig $generalConfig,
        AttributeConfig $attributeConfig,
        PricingAttributeConfig $pricingAttributeConfig,
        StockAttributeConfig $stockAttributeConfig,
        AgeAttributeConfig $ageAttributeConfig,
        ImageAttributeConfig $imageAttributeConfig,
        CustomAttributeConfig $customAttributeConfig,
        Json $json,
        ResourceConnection $resource,
        array $siteVariantPriceAttributes = [],
        array $siteVariantStockAttributes = [],
        array $siteVariantImageAttributes = [],
        array $siteVariantAgeAttributes = []
    ) {
        $this->dimensionProvider = $dimensionProvider;
        $this->indexerAction = $indexerAction;
        $this->engineProvider = $engineProvider;
        $this->dataHandlerFactory = $dataHandlerFactory;
        $this->productResource = $productResource;

        parent::__construct(
            $generalConfig,
            $attributeConfig,
            $pricingAttributeConfig,
            $stockAttributeConfig,
            $ageAttributeConfig,
            $imageAttributeConfig,
            $customAttributeConfig,
            $json,
            $resource,
            $siteVariantPriceAttributes,
            $siteVariantStockAttributes,
            $siteVariantImageAttributes,
            $siteVariantAgeAttributes
        );
        $this->resource = $resource;
    }

    /**
     * Set Skus
     *
     * @param array $skus
     */
    public function setSkus(array $skus)
    {
        $this->skus = $skus;
    }

    /**
     * Get Raw Product Data
     *
     * @param bool $isIncremental
     * @param bool $isVariants
     * @return array
     */
    protected function getRawProductData(bool $isIncremental, bool $isVariants) : array
    {
        $engine = $this->engineProvider->get();
        if (!($engine instanceof \Aligent\FredhopperIndexer\Model\ResourceModel\Engine)) {
            throw new \RuntimeException("Fredhopper is not configured as the search engine in Catalog Search");
        }

        $productIds = $this->productResource->getProductsIdsBySkus($this->skus);

        /** @var DataHandler $dataHandler */
        $dataHandler = $this->dataHandlerFactory->create();

        $products = [];
        foreach ($this->dimensionProvider->getIterator() as $dimension) {
            $scope = $dimension['scope'];
            $scopeId = (int)$scope->getValue();
            $documentSource = $this->indexerAction->rebuildStoreIndex($scopeId, $productIds);
            $documents = [];
            foreach ($documentSource as $sourceKey => $document) {
                $documents[$sourceKey] = $document;
            }
            $dataHandler->processDocuments($documents, $scopeId);
            foreach ($documents as $docKey => $doc) {
                if (!$isVariants) {
                    $product = [
                        'store_id' => $scopeId,
                        'product_type' => 'p',
                        'product_id' => $docKey,
                        'parent_id' => null,
                        'attribute_data' => json_encode($doc['product']),
                        'operation_type' => 'a',
                    ];
                    $products[] = $product;
                } else {
                    if (empty($doc['variants'])) {
                        continue;
                    }
                    foreach ($doc['variants'] as $variantKey => $variant) {
                        $product = [
                            'store_id' => $scopeId,
                            'product_type' => 'v',
                            'product_id' => $variantKey,
                            'parent_id' => $docKey,
                            'attribute_data' => json_encode($variant),
                            'operation_type' => 'a',
                        ];
                        $products[] = $product;
                    }
                }
            }
        }

        return $products;
    }
}
