<?php
declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

class Email extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var \Magento\Framework\Escaper
     */
    protected $escaper;

    /**
     * @var \Aligent\FredhopperIndexer\Model\IndexerInfo
     */
    protected $indexerInfo;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Escaper $escaper,
        \Aligent\FredhopperIndexer\Model\IndexerInfo $indexerInfo
    ) {
        parent::__construct($context);
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->escaper = $escaper;
        $this->indexerInfo = $indexerInfo;
    }

    public function getErrorHtml(array $errors): string
    {
        if (empty($errors)) {
            return '';
        }
        $output = "<ul>\n";
        foreach ($errors as $error) {
            $output .= '    <li>' . $this->escaper->escapeHtml($error) . "</li>\n";
        }
        $output .= "</ul>\n";
        return $output;
    }

    public function getIndexInfoTables(): string
    {
        $html = '';

        // Collate `bin/magento indexer:status` data
        $data = $this->indexerInfo->getIndexState();
        if (!empty($data)) {
            $html .= "<table border=\"1\">\n";
            $html .= '<tr>';
            $html .= '<th align="left">Indexer</th>';
            $html .= '<th align="left">Status</th>';
            $html .= '<th align="left">Schedule Status</th>';
            $html .= '<th align="left">Schedule Updated</th>';
            $html .= "<tr>\n";
            foreach ($data as $indexer) {
                $scheduleStatus = "{$indexer['schedule_status']} ({$indexer['schedule_backlog']} in backlog)";
                $html .= '<tr>';
                $html .= '<td>' . $this->escaper->escapeHtml($indexer['id']) .  '</td>';
                $html .= '<td>' . $this->escaper->escapeHtml($indexer['status']) .  '</td>';
                $html .= '<td>' . $this->escaper->escapeHtml($scheduleStatus) .  '</td>';
                $html .= '<td>' . $this->escaper->escapeHtml($indexer['schedule_updated']) .  '</td>';
                $html .= "</tr>\n";
            }
            $html .= "</table>\n";
        }

        $data = $this->indexerInfo->getFredhopperIndexState();
        if (!empty($data)) {
            $html .= "<br>\n";
            $html .= "<table border=\"1\">\n";
            $html .= '<tr>';
            $row = reset($data);
            foreach ($row as $heading => $junk) {
                $html .= '<th align="left">' . $this->escaper->escapeHtml(ucfirst($heading)) . '</th>';
            }
            $html .= "</tr>\n";
            foreach ($data as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>' . $this->escaper->escapeHtml($value) . '</td>';
                }
                $html .= "<tr>\n";
            }
            $html .= "</table>\n";
        }

        return $html;
    }

    public function getDeleteProductTables(): string
    {
        $data = $this->indexerInfo->getProductDeletes();

        if (empty($data)) {
            return '';
        }

        $num = count($data);
        $html = "<p>For reference, here is a random selection of $num products marked for deletion:</p>\n";

        $html .= "<table border=\"1\">\n";
        foreach ($data as $product) {
            $html .= '<tr>';
            $html .= '<td>' . $this->escaper->escapeHtml($product['sku']) . '</td>';
            $html .= '<td>' . $this->escaper->escapeHtml($product['name']) . '</td>';
            $html .= "<tr>\n";
        }
        $html .= "</table>\n";

        return $html;
    }

    /**
     * @param $template
     * @param array $to
     * @param array $data
     * @return bool
     */
    public function sendErrorEmail(array $to, array $errors): bool
    {
        $this->inlineTranslation->suspend();
        try {
            $templateVars = [
                'errors' => $this->getErrorHtml($errors),
                'indexer_info' => $this->getIndexInfoTables(),
                'delete_info' => $this->getDeleteProductTables(),
            ];

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(SanityCheckConfig::EMAIL_TEMPLATE)
                ->setTemplateOptions(
                    [
                        'area' => \Magento\Framework\App\Area::AREA_ADMINHTML,
                        'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID,
                    ]
                )
                ->setTemplateVars($templateVars)
                ->setFromByScope('general')
                ->addTo($to)
                ->getTransport();

            $transport->sendMessage();
            $this->inlineTranslation->resume();

            return true;

        } catch (\Exception $e) {
            $this->inlineTranslation->resume();
            return false;
        }
    }
}
