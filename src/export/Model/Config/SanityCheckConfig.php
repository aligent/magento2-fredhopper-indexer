<?php

declare(strict_types=1);

namespace Aligent\FredhopperExport\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class SanityCheckConfig
{
    public const string XML_PATH_PREFIX = 'fredhopper_indexer/sanity_check/';
    public const string XML_PATH_MIN_TOTAL = self::XML_PATH_PREFIX . 'total_products';
    public const string XML_PATH_MAX_DELETE = self::XML_PATH_PREFIX . 'delete_products';

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
}
