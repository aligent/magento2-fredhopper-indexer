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

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        \Magento\Framework\Escaper $escaper
    ) {
        parent::__construct($context);
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->escaper = $escaper;
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
