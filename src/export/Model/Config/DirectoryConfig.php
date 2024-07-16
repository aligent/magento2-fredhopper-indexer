<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;

class DirectoryConfig
{

    public const XML_PATH_EXPORT_DIRECTORY = 'fredhopper_indexer/general/export_directory';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getExportDirectory(): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH_EXPORT_DIRECTORY);
    }
}
