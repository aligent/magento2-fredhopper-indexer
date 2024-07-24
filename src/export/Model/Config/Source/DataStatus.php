<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Config\Source;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Framework\Data\OptionSourceInterface;

class DataStatus implements OptionSourceInterface
{

    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => ExportInterface::DATA_STATUS_UNKNOWN,
                'label' => __('Unknown'),
            ],
            [
                'value' => ExportInterface::DATA_STATUS_SCHEDULED,
                'label' => __('Scheduled'),
            ],
            [
                'value' => ExportInterface::DATA_STATUS_DELAYED,
                'label' => __('Delayed'),
            ],
            [
                'value' => ExportInterface::DATA_STATUS_RUNNING,
                'label' => __('Running'),
            ],
            [
                'value' => ExportInterface::DATA_STATUS_SUCCESS,
                'label' => __('Success'),
            ],
            [
                'value' => ExportInterface::DATA_STATUS_FAILURE,
                'label' => __('Failure'),
            ]
        ];
    }
}
