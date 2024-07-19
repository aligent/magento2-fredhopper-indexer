<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class ExportConfig
{

    private const MIN_RETENTION_DAYS = 1;

    public const XML_PATH_EXPORT_DIRECTORY = 'fredhopper_indexer/general/export_directory';
    public const XML_PATH_EXPORT_RETENTION = 'fredhopper_indexer/general/export_retention';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Gets the configured export directory
     *
     * @return string
     */
    public function getExportDirectory(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_EXPORT_DIRECTORY);
    }

    /**
     * Get the retention time for export files
     *
     * @return int
     */
    public function getExportRetention(): int
    {
        return max(self::MIN_RETENTION_DAYS, (int)$this->scopeConfig->getValue(self::XML_PATH_EXPORT_RETENTION));
    }
}
