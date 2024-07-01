<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Indexer\Data\Product;

class VariantToProductMapping
{
    /** @var array */
    private array $variantToProductMapping = [];

    /**
     * Add a mapping to the array
     *
     * @param int $variantId
     * @param int $productId
     * @return void
     */
    public function addMapping(int $variantId, int $productId): void
    {
        $this->variantToProductMapping[$variantId] = $productId;
    }

    /**
     * Retrieve the mapping array
     * @return array
     */
    public function  getVariantToProductMapping(): array
    {
        return $this->variantToProductMapping;
    }
}
