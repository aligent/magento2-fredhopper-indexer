<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Controller\Adminhtml\Exports;

use Aligent\FredhopperExport\Api\Data\ExportInterface;
use Aligent\FredhopperExport\Model\Data\Export;
use Aligent\FredhopperExport\Model\Data\ExportFactory;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export as ExportResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Filesystem\Io\File as Filesystem;
use Psr\Log\LoggerInterface;

class Data extends Action
{

    public const string ADMIN_RESOURCE = 'Aligent_FredhopperExport::manage';

    /**
     * @param Context $context
     * @param ExportFactory $exportFactory
     * @param ExportResource $exportResource
     * @param FileFactory $fileFactory
     * @param LoggerInterface $logger
     * @param Filesystem $filesystem
     */
    public function __construct(
        readonly Context $context,
        private readonly ExportFactory $exportFactory,
        private readonly ExportResource $exportResource,
        private readonly FileFactory $fileFactory,
        private readonly LoggerInterface $logger,
        private readonly Filesystem $filesystem
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResponseInterface
    {
        $exportId = (int)$this->getRequest()->getParam('id');
        if (empty($exportId)) {
            $this->getMessageManager()->addErrorMessage(__('Export ID is required.'));
            $this->_redirect('*/*/index');
            return $this->_response;
        }
        $isZip = (bool)$this->getRequest()->getParam('zip');
        /** @var Export $export */
        $export = $this->exportFactory->create();
        $this->exportResource->load($export, $exportId);
        if ($export->isEmpty()) {
            $this->getMessageManager()->addErrorMessage(__('Could not load export data.'));
            $this->_redirect('*/*/index');
            return $this->_response;
        }
        $directory = $export->getDirectory();
        $filename = match ($export->getExportType()) {
            ExportInterface::EXPORT_TYPE_INCREMENTAL => ExportInterface::ZIP_FILENAME_INCREMENTAL,
            ExportInterface::EXPORT_TYPE_FULL => ExportInterface::ZIP_FILENAME_FULL,
            ExportInterface::EXPORT_TYPE_SUGGEST => ExportInterface::ZIP_FILENAME_SUGGEST,
            default => ''
        };
        if (empty($filename)) {
            $this->getMessageManager()->addErrorMessage(__('Could not download data file.'));
            $this->_redirect('*/*/index');
            return $this->_response;
        }

        $filename = $directory . DIRECTORY_SEPARATOR . $filename;
        $name = $this->filesystem->getPathInfo($filename)['basename'] ?? $filename;
        try {
            return $this->fileFactory->create(
                $name,
                [
                    'type' => 'filename',
                    'value' => $filename,
                    'rm' => false
                ],
                DirectoryList::VAR_DIR,
                'application/zip'
            );
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e]);
            $this->getMessageManager()->addErrorMessage(__('Could not download data file.'));
            $this->_redirect('*/*/index');
            return $this->_response;
        }
    }
}
