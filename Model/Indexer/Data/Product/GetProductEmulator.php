<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Indexer\Data\Product;

use Magento\Framework\DataObject;

class GetProductEmulator
{

    /** @var array */
    private array $productEmulators = [];

    /**
     * Emulates a product of the given type using a DataObject
     *
     * @param string $typeId
     * @return DataObject
     */
    public function execute(string $typeId): DataObject
    {
        if (!isset($this->productEmulators[$typeId])) {
            $this->productEmulators[$typeId] = new DataObject();
            $this->productEmulators[$typeId]->setData('type_id', $typeId);
        }
        return $this->productEmulators[$typeId];
    }
}
