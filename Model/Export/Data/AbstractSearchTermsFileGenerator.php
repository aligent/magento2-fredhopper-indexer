<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Api\Export\FileGeneratorInterface;
use Aligent\FredhopperIndexer\Helper\SuggestConfig;
use Magento\Framework\Filesystem\Driver\File;

abstract class AbstractSearchTermsFileGenerator implements FileGeneratorInterface
{
    protected const FILENAME = '';
    private const HEADER_ROW = ['searchterm', 'locale'];
    private const DELIMITER = "\t\t";

    protected SuggestConfig $suggestConfig;

    private File $fileSystem;

    public function __construct(
        SuggestConfig $suggestConfig,
        File $fileSystem
    ) {
        $this->suggestConfig = $suggestConfig;
        $this->fileSystem = $fileSystem;
    }

    /**
     * @inheritDoc
     */
    public function generateFile(string $directory): string
    {
        $defaultLocale = $this->suggestConfig->getDefaultLocale();
        $searchTerms = $this->getSearchTerms();
        if (empty($searchTerms)) {
            return '';
        }

        $filename = $directory . DIRECTORY_SEPARATOR . static::FILENAME;
        $fileContent = $this->addRow(self::HEADER_ROW);

        foreach ($searchTerms as $searchTerm) {
            $row = [$searchTerm['search_term'], $defaultLocale];
            $fileContent .= "\n";
            $fileContent .= $this->addRow($row);
        }

        try {
            $this->fileSystem->filePutContents($filename, $fileContent);
            return $filename;
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
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

    abstract protected function getSearchTerms() : array;
}
