<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Controller\Adminhtml\Exports;

use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\Data\ExportFactory;
use Aligent\FredhopperExport\Model\DownloadQualityReport;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\FileInterface;
use Magento\Framework\App\ResponseInterface;

class Report extends Action
{

    public const string ADMIN_RESOURCE = 'Aligent_FredhopperExport::manage';

    /**
     * @param Context $context
     * @param ExportFactory $exportFactory
     * @param ExportResource $exportResource
     * @param DownloadQualityReport $downloadQualityReport
     * @param FileInterface $response
     */
    public function __construct(
        readonly Context $context,
        private readonly ExportFactory $exportFactory,
        private readonly ExportResource $exportResource,
        private readonly DownloadQualityReport $downloadQualityReport,
        private readonly FileInterface $response
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResponseInterface
    {
        $exportId = (int)$this->getRequest()->getParam('export_id');
        if (empty($exportId)) {
            $this->getMessageManager()->addErrorMessage(__('Export ID is required.'));
            $this->_redirect('*/*/index');
            return $this->_response;
        }
        /** @var Export $export */
        $export = $this->exportFactory->create();
        $this->exportResource->load($export, $exportId);
        if ($export->isEmpty()) {
            $this->getMessageManager()->addErrorMessage(__('Could not load export data.'));
            $this->_redirect('*/*/index');
            return $this->_response;
        }
        $directory = $export->getDirectory();
        $triggerId = $export->getTriggerId();
        $filename = $this->downloadQualityReport->execute($directory, $triggerId);
        if (empty($filename)) {
            $this->getMessageManager()->addErrorMessage(__('Could not download quality report.'));
            $this->_redirect('*/*/index');
            return $this->_response;
        }

        $this->response->setFilePath($filename);
        return $this->response;
    }
}
