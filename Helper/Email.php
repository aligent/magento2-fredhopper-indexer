<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

use Aligent\FredhopperIndexer\Model\IndexerInfo;
use Magento\Framework\App\Area;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\Store;

class Email extends AbstractHelper
{
    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var Escaper
     */
    protected $escaper;

    /**
     * @var IndexerInfo
     */
    protected $indexerInfo;

    public function __construct(
        Context $context,
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        Escaper $escaper,
        IndexerInfo $indexerInfo
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

    /**
     * @param array $to
     * @param array $errors
     * @return bool
     */
    public function sendErrorEmail(array $to, array $errors): bool
    {
        $this->inlineTranslation->suspend();
        try {
            $templateVars = [
                'errors' => $this->getErrorHtml($errors),
                'indexer_info' => $this->getIndexInfoTables(),
            ];

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(SanityCheckConfig::EMAIL_TEMPLATE)
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_ADMINHTML,
                        'store' => Store::DEFAULT_STORE_ID,
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
