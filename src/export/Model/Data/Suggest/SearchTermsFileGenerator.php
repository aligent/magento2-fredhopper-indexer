<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Model\Data\Suggest;

use Aligent\FredhopperExport\Api\FileGeneratorInterface;
use Aligent\FredhopperCommon\Model\Config\GeneralConfig;
use Aligent\FredhopperExport\Model\Config\SuggestConfig;
use Magento\Framework\Filesystem\Driver\File;

class SearchTermsFileGenerator implements FileGeneratorInterface
{

    private const string BLACKLIST_FILENAME = 'blacklist.csv';
    private const string WHITELIST_FILENAME = 'whitelist.csv';
    private const array HEADER_ROW = ['searchterm', 'locale'];
    private const string DELIMITER = "\t\t";

    /**
     * @param GeneralConfig $generalConfig
     * @param SuggestConfig $suggestConfig
     * @param File $file
     * @param bool $isBlacklist
     */
    public function __construct(
        private readonly GeneralConfig $generalConfig,
        private readonly SuggestConfig $suggestConfig,
        private readonly File $file,
        private readonly bool $isBlacklist
    ) {
    }

    /**
     * @inheritDoc
     */
    public function generateFile(string $directory): string
    {
        $defaultLocale = $this->generalConfig->getDefaultLocale();
        $searchTerms = $this->getSearchTerms();

        $filename = $directory . DIRECTORY_SEPARATOR .
            ($this->isBlacklist ? self::BLACKLIST_FILENAME : self::WHITELIST_FILENAME);
        $fileContent = $this->addRow(self::HEADER_ROW);

        foreach ($searchTerms as $searchTerm) {
            $row = [$searchTerm['search_term'], $defaultLocale];
            $fileContent .= "\n";
            $fileContent .= $this->addRow($row);
        }

        try {
            $this->file->filePutContents($filename, $fileContent);
            return $filename;
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Adds a row to the generated file
     *
     * @param array $data
     * @return string
     */
    private function addRow(array $data): string
    {
        $rowContent = '';
        foreach ($data as $column) {
            $rowContent .= $column . self::DELIMITER;
        }
        // remove trailing delimiter
        return rtrim($rowContent, self::DELIMITER);
    }

    /**
     * Get search terms from config
     *
     * @return array
     */
    private function getSearchTerms() : array
    {
        if ($this->isBlacklist) {
            return $this->suggestConfig->getBlacklistSearchTerms();
        }
        return $this->suggestConfig->getWhitelistSearchTerms();
    }
}
