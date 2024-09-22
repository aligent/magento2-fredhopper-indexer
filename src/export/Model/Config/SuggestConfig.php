<?php

declare(strict_types=1);

namespace Aligent\FredhopperExport\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

class SuggestConfig
{
    public const string XML_PATH_PREFIX = 'fredhopper_indexer/suggest/';
    public const string XML_PATH_BLACKLIST_TERMS = self::XML_PATH_PREFIX . 'blacklist_terms';
    public const string XML_PATH_WHITELIST_TERMS = self::XML_PATH_PREFIX . 'whitelist_terms';


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
            $unserializedValue = $this->json->unserialize($configValue ?? '[]');
            // it's possible (though very unlikely) that this will not be an array
            $this->blacklistSearchTerms = is_array($unserializedValue) ? $unserializedValue : [];
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
            $unserializedValue = $this->json->unserialize($configValue ?? '[]');
            // it's possible (though very unlikely) that this will not be an array
            $this->whitelistSearchTerms = is_array($unserializedValue) ? $unserializedValue : [];
        }
        return $this->whitelistSearchTerms;
    }
}
