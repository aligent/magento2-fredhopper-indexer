<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Ui\Component\Listing\Column;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class DataStatus extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                switch ($item[$this->getData('name')]) {
                    case ExportInterface::DATA_STATUS_UNKNOWN:
                        $class = 'grid-fh-data-status-unknown';
                        $text = __('Unknown');
                        break;
                    case ExportInterface::DATA_STATUS_SCHEDULED:
                        $class = 'grid-fh-data-status-scheduled';
                        $text = __('Scheduled');
                        break;
                    case ExportInterface::DATA_STATUS_DELAYED:
                        $class = 'grid-fh-data-status-delayed';
                        $text = __('Delayed');
                        break;
                    case ExportInterface::DATA_STATUS_RUNNING:
                        $class = 'grid-fh-data-status-running';
                        $text = __('Running');
                        break;
                    case ExportInterface::DATA_STATUS_SUCCESS:
                        $class = 'grid-fh-data-status-success';
                        $text = __('Success');
                        break;
                    case ExportInterface::DATA_STATUS_FAILURE:
                        $class = 'grid-fh-data-status-failure';
                        $text = __('Failure');
                        break;
                    default:
                        $class = 'grid-fh-data-status-unknown';
                        $text = __('Unknown');
                        break;
                }

                $html = '<span class="' . $class . '">' . $text . '</span>';
                $item[$this->getData('name')] = $html;
            }
        }
        return $dataSource;
    }
}
