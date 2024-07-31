<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Ui\Component\Listing\Column;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    private const URL_PATH_REPORT = 'fredhopper/exports/report';

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item[ExportInterface::FIELD_EXPORT_ID])) {
                    // if the export is already complete, we can download a quality report
                    if (($item['status'] ?? '') === ExportInterface::STATUS_COMPLETE) {
                        $item[$this->getData('name')] = [
                            'download' => [
                                'href' => $this->urlBuilder->getUrl(
                                    self::URL_PATH_REPORT,
                                    [
                                        'id' => $item[ExportInterface::FIELD_EXPORT_ID]
                                    ]
                                ),
                                'label' => __('Download Quality Report'),
                            ]
                        ];
                    }
                }
            }
        }
        return $dataSource;
    }

}
