<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Controller\Adminhtml\Exports;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface as HttpGetActionInterface;

class Index extends Action implements HttpGetActionInterface
{

    const string ADMIN_RESOURCE = 'Aligent_FredhopperExport::manage';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        readonly Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @inheritDoc
     */
    public function execute(): Page
    {
        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Aligent_FredhopperExport::exports');
        $resultPage->getConfig()->getTitle()->prepend(__('Fredhopper Exports'));

        return $resultPage;
    }
}
