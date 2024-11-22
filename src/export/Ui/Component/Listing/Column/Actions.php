<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Ui\Component\Listing\Column;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class Actions extends Column
{
    private const string URL_PATH_REPORT = 'fredhopper/exports/report';
    private const string URL_PATH_DATA_FILE = 'fredhopper/exports/data';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param ResourceConnection $resourceConnection
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly ResourceConnection $resourceConnection,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @inheritDoc
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                if (isset($item[ExportInterface::FIELD_EXPORT_ID])) {
                    $exportId = (int)$item[ExportInterface::FIELD_EXPORT_ID];
                    // give link to download export zip file
                    $actions = [
                        'download_data' => [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_DATA_FILE,
                                [
                                    'id' => $item[ExportInterface::FIELD_EXPORT_ID]
                                ]
                            ),
                            'label' => __('Download Data File'),
                            'target' => '_blank'
                        ]
                    ];
                    // if the export is already complete, we can download a quality report
                    if ($this->canDownloadReport($exportId)) {
                        $actions['download_report'] = [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_REPORT,
                                [
                                    'id' => $item[ExportInterface::FIELD_EXPORT_ID]
                                ]
                            ),
                            'label' => __('Download Quality Report'),
                            'target' => '_blank'
                        ];
                        $actions['download_report_zip'] = [
                            'href' => $this->urlBuilder->getUrl(
                                self::URL_PATH_REPORT,
                                [
                                    'id' => $item[ExportInterface::FIELD_EXPORT_ID],
                                    'zip' => true
                                ]
                            ),
                            'label' => __('Download ZIP Report'),
                            'target' => '_blank',
                        ];
                    }
                    $item[$this->getData('name')] = $actions;
                }
            }
        }
        return $dataSource;
    }

    /**
     * Get the status of the given export
     *
     * @param int $exportId
     * @return bool
     */
    private function canDownloadReport(int $exportId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select();
        $select->from(
            ExportResource::MAIN_TABLE_NAME,
            [ExportInterface::FIELD_EXPORT_TYPE, ExportInterface::FIELD_STATUS]
        );
        $select->where('export_id = ?', $exportId);
        $result = $connection->fetchRow($select);
        if (($result[ExportInterface::FIELD_STATUS] ?? '') !== ExportInterface::STATUS_COMPLETE) {
            return false;
        }
        if ($result[ExportInterface::FIELD_EXPORT_TYPE ?? ''] === ExportInterface::EXPORT_TYPE_SUGGEST) {
            return false;
        }
        return true;
    }
}
