<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Ui\Component\Listing\Column;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class ExportType extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $text = match ($item[$this->getData('name')]) {
                    ExportInterface::EXPORT_TYPE_FULL => __('Full'),
                    ExportInterface::EXPORT_TYPE_INCREMENTAL => __('Incremental'),
                    ExportInterface::EXPORT_TYPE_SUGGEST => __('Suggest'),
                    default => __('Unknown'),
                };
                $html = '<span>' . $text . '</span>';
                $item[$this->getData('name')] = $html;
            }
        }
        return $dataSource;
    }
}
