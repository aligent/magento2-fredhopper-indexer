<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Api\Export\FileGeneratorInterface;

class BlacklistFileGenerator extends AbstractSearchTermsFileGenerator implements FileGeneratorInterface
{
    protected const FILENAME = 'blacklist.csv';

    protected function getSearchTerms(): array
    {
        return $this->suggestConfig->getBlacklistSearchTerms();
    }
}
