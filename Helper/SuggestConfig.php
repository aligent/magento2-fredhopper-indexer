<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

class SuggestConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/suggest/';
    public const XML_PATH_BLACKLIST_TERMS = self::XML_PATH_PREFIX . 'blacklist_terms';
    public const XML_PATH_WHITELIST_TERMS = self::XML_PATH_PREFIX . 'whitelist_terms';


    /** @var string[] */
    private array $blacklistSearchTerms;
    /** @var string[] */
    private array $whitelistSearchTerms;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json
    ) {
    }

    /**
     * @return array
     */
    public function getBlacklistSearchTerms(): array
    {
        if (!isset($this->blacklistSearchTerms)) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_BLACKLIST_TERMS);
            $this->blacklistSearchTerms = $this->json->unserialize($configValue ?? '[]');
        }
        return $this->blacklistSearchTerms;
    }

    /**
     * @return array
     */
    public function getWhitelistSearchTerms(): array
    {
        if (!isset($this->whitelistSearchTerms)) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_WHITELIST_TERMS);
            $this->whitelistSearchTerms = $this->json->unserialize($configValue ?? '[]');
        }
        return $this->whitelistSearchTerms;
    }
}
