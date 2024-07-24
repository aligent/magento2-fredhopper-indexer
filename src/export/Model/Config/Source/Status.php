<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Config\Source;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => ExportInterface::STATUS_PENDING,
                'label' => __('Pending')
            ],
            [
                'value' => ExportInterface::STATUS_UPLOADED,
                'label' => __('Uploaded')
            ],
            [
                'value' => ExportInterface::STATUS_TRIGGERED,
                'label' => __('Triggered')
            ],
            [
                'value' => ExportInterface::STATUS_COMPLETE,
                'label' => __('Complete')
            ],
            [
                'value' => ExportInterface::STATUS_ERROR,
                'label' => __('Error')
            ],
            [
                'value' => ExportInterface::STATUS_INVALID,
                'label' => __('Invalid')
            ]
        ];
    }
}
