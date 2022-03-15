<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Data;

use Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider;
use Magento\Framework\Data\OptionSourceInterface;

class CreatedAtOptionSource implements OptionSourceInterface
{
    /**
     * @var DataProvider
     */
    protected $dataProvider;

    protected $options = null;

    public function __construct(
        DataProvider $dataProvider
    ) {
        $this->dataProvider = $dataProvider;
    }

    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $options = [
                ['value' => 'created_at', 'label' => __('Created at')],
            ];
            $ids = [];
            foreach ($this->dataProvider->getSearchableAttributes() as $attr) {
                $code = $attr->getAttributeCode();
                // All attributes are duplicated (one entry for id, one for attribute_code) :/
                if (isset($ids[$code]) || $attr->getBackendType() !== 'datetime') {
                    continue;
                }
                $options[] = ['value' => $code, 'label' => $attr->getFrontendLabel()];
                $ids[$code] = true;
            }
            $this->options = $options;
        }
        return $this->options;
    }
}
