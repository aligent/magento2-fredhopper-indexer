<?php
namespace Aligent\FredhopperIndexer\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;

class SuggestConfig extends GeneralConfig
{
    public const XML_PATH_PREFIX = 'fredhopper_indexer/suggest/';
    public const XML_PATH_BLACKLIST_TERMS = self::XML_PATH_PREFIX . 'blacklist_terms';
    public const XML_PATH_WHITELIST_TERMS = self::XML_PATH_PREFIX . 'whitelist_terms';

    /**
     * @var Json
     */
    protected $json;

    /** @var string[] */
    protected $blacklistSearchTerms;
    /** @var string[] */
    protected $whitelistSearchTerms;

    public function __construct(
        Context $context,
        Resolver $localeResolver,
        StoreManagerInterface $storeManager,
        Json $json
    ) {
        parent::__construct($context, $localeResolver, $storeManager);
        $this->json = $json;
    }

    public function getBlacklistSearchTerms()
    {
        if ($this->blacklistSearchTerms === null) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_BLACKLIST_TERMS);
            $this->blacklistSearchTerms = $this->json->unserialize($configValue ?? '[]');
        }
        return $this->blacklistSearchTerms;
    }

    public function getWhitelistSearchTerms()
    {
        if ($this->whitelistSearchTerms === null) {
            $configValue = $this->scopeConfig->getValue(self::XML_PATH_WHITELIST_TERMS);
            $this->whitelistSearchTerms = $this->json->unserialize($configValue ?? '[]');
        }
        return $this->whitelistSearchTerms;
    }
}
