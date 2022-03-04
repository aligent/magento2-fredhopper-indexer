<?php
namespace Aligent\FredhopperIndexer\Model\Indexer\Data;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\View\Asset\ImageFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\View\ConfigInterface;
use Magento\Store\Model\App\Emulation;

class ImageFieldsProvider implements AdditionalFieldsProviderInterface
{
    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;
    /**
     * @var ParamsBuilder
     */
    protected $paramsBuilder;
    /**
     * @var ImageFactory
     */
    protected $imageAssetFactory;
    /**
     * @var ConfigInterface
     */
    protected $presentationConfig;
    /**
     * @var State
     */
    protected $appState;
    /**
     * @var Emulation
     */
    protected $emulation;
    /**
     * @var array
     */
    protected $imageAttributeConfig;
    /**
     * @var array
     */
    protected $imageParams = [];

    public function __construct(
        CollectionFactory $productCollectionFactory,
        ParamsBuilder $paramsBuilder,
        ImageFactory $imageAssetFactory,
        ConfigInterface $presentationConfig,
        State $appState,
        Emulation $emulation,
        $imageAttributeConfig = []
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->paramsBuilder = $paramsBuilder;
        $this->imageAssetFactory = $imageAssetFactory;
        $this->presentationConfig = $presentationConfig;
        $this->appState = $appState;
        $this->emulation = $emulation;
        $this->imageAttributeConfig = $imageAttributeConfig;
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId)
    {
        if (empty($this->imageAttributeConfig)) {
            return [];
        }
        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter($productIds);
        $collection->addStoreFilter($storeId);
        foreach ($this->imageAttributeConfig as $fredhopperAttribute => $imageConfig) {
            $collection->addAttributeToSelect($imageConfig['attribute_code']);
        }

        $products = $collection->getItems();
        $result = [];
        foreach ($products as $productId => $product) {
            foreach ($this->imageAttributeConfig as $fredhopperAttribute => $imageConfig) {
                $imageParams = $this->getImageParamsForStore($imageConfig['display_area'], $storeId);
                /** @var \Magento\Catalog\Model\View\Asset\Image $asset */
                $asset = $this->imageAssetFactory->create([
                        'miscParams' => $imageParams,
                        'filePath' => $product->getData($imageConfig['attribute_code']),
                ]);
                $result[$productId][$fredhopperAttribute] = $asset->getUrl();
            }
        }
        return $result;
    }

    protected function getImageParamsForStore($imageDisplayArea, $storeId)
    {
        if (!isset($this->imageParams[$imageDisplayArea][$storeId])) {
            try {
                $this->emulation->startEnvironmentEmulation(
                    $storeId,
                    Area::AREA_FRONTEND,
                    true
                );
                $imageArguments = $this->appState->emulateAreaCode(
                    Area::AREA_FRONTEND,
                    [$this, 'getImageParams'],
                    ['imageDisplayArea' => $imageDisplayArea]
                );
                $this->imageParams[$imageDisplayArea][$storeId] = $this->paramsBuilder->build($imageArguments);
            } catch (\Exception $e) {
                // unexpected error
                $this->imageParams[$imageDisplayArea][$storeId] = $this->paramsBuilder->build([]);
            } finally {
                // always stop emulation
                $this->emulation->stopEnvironmentEmulation();
            }
        }
        return $this->imageParams[$imageDisplayArea][$storeId];
    }

    public function getImageParams($imageDisplayArea)
    {
        return $this->presentationConfig->getViewConfig()->getMediaAttributes(
            'Magento_Catalog',
            Image::MEDIA_TYPE_CONFIG_NODE,
            $imageDisplayArea
        );
    }
}
