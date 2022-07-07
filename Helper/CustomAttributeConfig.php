<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Framework\Locale\Resolver;
use Magento\Store\Model\StoreManagerInterface;

class CustomAttributeConfig extends GeneralConfig
{

    /** @var array */
    private array $customAttributeData;
    /** string[] */
    private array $siteVariantCustomAttributes;

    public function __construct(
        Context $context,
        Resolver $localeResolver,
        StoreManagerInterface $storeManager,
        array $customAttributeData
    ) {
        parent::__construct($context, $localeResolver, $storeManager);

        $this->customAttributeData = $customAttributeData;
    }

    public function getCustomAttributeData(): array
    {
        return $this->customAttributeData;
    }

    /**
     * @return array
     */
    public function getSiteVariantCustomAttributes(): array
    {
        if (!isset($this->siteVariantCustomAttributes)) {
            $this->siteVariantCustomAttributes = [];
            foreach ($this->customAttributeData as $attributeCode => $attributeData) {
                if ($attributeData['is_site_variant'] ?? false) {
                    $this->siteVariantCustomAttributes[] = $attributeCode;
                }
            }
        }
        return $this->siteVariantCustomAttributes;
    }
}
