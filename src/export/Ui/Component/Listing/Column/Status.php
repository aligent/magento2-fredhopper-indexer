<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Ui\Component\Listing\Column;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Ui\Component\Listing\Columns\Column;

class Status extends Column
{
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                switch ($item[$this->getData('name')]) {
                    case ExportInterface::STATUS_PENDING:
                        $class = 'grid-fh-status-pending';
                        $text = __('Pending');
                        break;
                    case ExportInterface::STATUS_UPLOADED:
                        $class = 'grid-fh-status-uploaded';
                        $text = __('Uploaded');
                        break;
                    case ExportInterface::STATUS_TRIGGERED:
                        $class = 'grid-fh-status-triggered';
                        $text = __('Triggered');
                        break;
                    case ExportInterface::STATUS_COMPLETE:
                        $class = 'grid-fh-status-complete';
                        $text = __('Complete');
                        break;
                    case ExportInterface::STATUS_ERROR:
                        $class = 'grid-fh-status-error';
                        $text = __('Error');
                        break;
                    case ExportInterface::STATUS_INVALID:
                        $class = 'grid-fh-status-invalid';
                        $text = __('Invalid');
                        break;
                    default:
                        $class = 'grid-fh-status-unknown';
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
