<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Indexer\Data\Product;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Type\AbstractType;

class GetProductTypeInstance
{

    /** @var array */
    private array $productTypes = [];

    public function __construct(
        private readonly Type $catalogProductType,
        private readonly GetProductEmulator $getProductEmulator
    ) {
    }

    /**
     * Get an instance of the given product type class
     *
     * @param string $typeId
     * @return AbstractType
     */
    public function execute(string $typeId): AbstractType
    {
        if (!isset($this->productTypes[$typeId])) {
            /** @var Product $productEmulator */
            $productEmulator = $this->getProductEmulator->execute($typeId);
            $this->productTypes[$typeId] = $this->catalogProductType->factory($productEmulator);
        }

        return $this->productTypes[$typeId];
    }
}
