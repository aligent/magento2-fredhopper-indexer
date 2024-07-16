<?php

declare(strict_types=1);

namespace Aligent\FredhopperExport\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class SanityCheckConfig
{
    public const EMAIL_TEMPLATE = 'fh_indexer_sanity_check_email_template';
    public const XML_PATH_PREFIX = 'fredhopper_indexer/sanity_check/';
    public const XML_PATH_MIN_TOTAL = self::XML_PATH_PREFIX . 'total_products';
    public const XML_PATH_MAX_DELETE = self::XML_PATH_PREFIX . 'delete_products';
    public const XML_PATH_REPORT_EMAIL = self::XML_PATH_PREFIX . 'report_email';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return int
     */
    public function getMinTotalProducts(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_TOTAL);
    }

    /**
     * @return int
     */
    public function getMaxDeleteProducts(): int
    {
        return (int)$this->scopeConfig->getValue(self::XML_PATH_MAX_DELETE);
    }

    /**
     * @return array
     */
    public function getErrorEmailRecipients(): array
    {
        $rawConfig = $this->scopeConfig->getValue(self::XML_PATH_REPORT_EMAIL) ?? '';
        $emailRecipients = [];
        foreach (preg_split('/,\s*/', $rawConfig) as $recipient) {
            if (!empty($recipient)) {
                $emailRecipients[] = $recipient;
            }
        }
        return $emailRecipients;
    }
}
