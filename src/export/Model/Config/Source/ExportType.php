<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Config\Source;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Framework\Data\OptionSourceInterface;

class ExportType implements OptionSourceInterface
{

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => ExportInterface::EXPORT_TYPE_FULL,
                'label' => __('Full')
            ],
            [
                'value' => ExportInterface::EXPORT_TYPE_INCREMENTAL,
                'label' => __('Incremental')
            ],
            [
                'value' => ExportInterface::EXPORT_TYPE_SUGGEST,
                'label' => __('Suggest')
            ]
        ];
    }
}
