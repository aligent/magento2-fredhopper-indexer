<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Data;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product\Image\ParamsBuilder;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\View\Asset\Image as ImageAsset;
use Magento\Catalog\Model\View\Asset\ImageFactory;
use Magento\Framework\App\Area;
use Magento\Framework\View\ConfigInterface;
use Magento\Store\Model\App\Emulation;

class ImageFieldsProvider implements AdditionalFieldsProviderInterface
{
    /**
     * @var array
     */
    private array $imageParams = [];

    /**
     * @param CollectionFactory $productCollectionFactory
     * @param ParamsBuilder $paramsBuilder
     * @param ImageFactory $imageAssetFactory
     * @param ConfigInterface $presentationConfig
     * @param Emulation $emulation
     * @param array $imageAttributeConfig
     */
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly ParamsBuilder $paramsBuilder,
        private readonly ImageFactory $imageAssetFactory,
        private readonly ConfigInterface $presentationConfig,
        private readonly Emulation $emulation,
        private readonly array $imageAttributeConfig = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getFields(array $productIds, $storeId): array
    {
        if (empty($this->imageAttributeConfig)) {
            return [];
        }
        $collection = $this->productCollectionFactory->create();
        $collection->addIdFilter($productIds);
        $collection->addStoreFilter($storeId);
        foreach ($this->imageAttributeConfig as $imageConfig) {
            $collection->addAttributeToSelect($imageConfig['attribute_code']);
        }

        $products = $collection->getItems();
        $result = [];
        foreach ($products as $productId => $product) {
            foreach ($this->imageAttributeConfig as $fredhopperAttribute => $imageConfig) {
                $imageParams = $this->getImageParamsForStore($imageConfig['display_area'], (int)$storeId);
                /** @var ImageAsset $asset */
                $asset = $this->imageAssetFactory->create([
                        'miscParams' => $imageParams,
                        'filePath' => $product->getData($imageConfig['attribute_code']),
                ]);
                $result[$productId][$fredhopperAttribute] = $asset->getUrl();
            }
        }
        return $result;
    }

    /**
     * Get image parameters for a given store
     *
     * @param string $imageDisplayArea
     * @param int $storeId
     * @return array
     */
    private function getImageParamsForStore(string $imageDisplayArea, int $storeId): array
    {
        if (!isset($this->imageParams[$imageDisplayArea][$storeId])) {
            try {
                $this->emulation->startEnvironmentEmulation(
                    $storeId,
                    Area::AREA_FRONTEND,
                    true
                );
                $imageArguments = $this->getImageParams($imageDisplayArea);
                $this->imageParams[$imageDisplayArea][$storeId] = $this->paramsBuilder->build($imageArguments);
            } catch (\Exception) {
                // unexpected error
                $this->imageParams[$imageDisplayArea][$storeId] = $this->paramsBuilder->build([]);
            } finally {
                // always stop emulation
                $this->emulation->stopEnvironmentEmulation();
            }
        }
        return $this->imageParams[$imageDisplayArea][$storeId];
    }

    /**
     * Get image parameters
     *
     * @param string $imageDisplayArea
     * @return array
     */
    public function getImageParams(string $imageDisplayArea): array
    {
        return $this->presentationConfig->getViewConfig()->getMediaAttributes(
            'Magento_Catalog',
            Image::MEDIA_TYPE_CONFIG_NODE,
            $imageDisplayArea
        );
    }
}
