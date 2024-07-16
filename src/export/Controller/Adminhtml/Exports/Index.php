<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Controller\Adminhtml\Exports;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\Model\View\Result\PageFactory;

class Index extends Action
{

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
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
        $resultPage->setActiveMenu('Aligent_FredhopperExport::fredhopper_exports');
        $resultPage->getConfig()->getTitle()->prepend(__('Fredhopper Exports'));

        return $resultPage;
    }
}
