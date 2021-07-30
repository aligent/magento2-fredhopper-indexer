<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Data;

class CreatedAtOptionSource implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @var \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider
     */
    protected $dataProvider;

    protected $options = null;

    public function __construct(
        \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider $dataProvider
    ) {
        $this->dataProvider = $dataProvider;
    }

    public function toOptionArray()
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
