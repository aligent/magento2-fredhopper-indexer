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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\ConfigInterface;
use Magento\Store\Model\App\Emulation;
use Psr\Log\LoggerInterface;

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
     * @param LoggerInterface $logger
     * @param array $imageAttributeConfig
     */
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly ParamsBuilder $paramsBuilder,
        private readonly ImageFactory $imageAssetFactory,
        private readonly ConfigInterface $presentationConfig,
        private readonly Emulation $emulation,
        private readonly LoggerInterface $logger,
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
                $path = $product->getData($imageConfig['attribute_code']);
                try {
                    $imageUrl = $this->getImageUrlForStore($imageConfig['display_area'], (int)$storeId, $path);
                } catch (LocalizedException $e) {
                    $this->logger->error($e->getMessage(), ['exception' => $e]);
                    continue;
                }

                $result[$productId][$fredhopperAttribute] = $imageUrl;
            }
        }
        return $result;
    }

    /**
     * Get image url for a given store and path
     *
     * @param string $imageDisplayArea
     * @param int $storeId
     * @param string $path
     * @return string
     * @throws LocalizedException
     */
    private function getImageUrlForStore(string $imageDisplayArea, int $storeId, string $path): string
    {
        $this->emulation->startEnvironmentEmulation(
            $storeId,
            Area::AREA_FRONTEND,
            true
        );

        if (!isset($this->imageParams[$imageDisplayArea][$storeId])) {
            try {
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
        /** @var ImageAsset $asset */
        $asset = $this->imageAssetFactory->create(
            [
                'miscParams' => $this->imageParams[$imageDisplayArea][$storeId],
                'filePath' => $path
            ]
        );
        $url = $asset->getUrl();
        // always stop emulation
        $this->emulation->stopEnvironmentEmulation();
        return $url;
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
