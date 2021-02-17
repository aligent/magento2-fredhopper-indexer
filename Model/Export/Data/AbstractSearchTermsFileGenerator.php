<?php
namespace Aligent\FredhopperIndexer\Model\Export\Data;

abstract class AbstractSearchTermsFileGenerator implements \Aligent\FredhopperIndexer\Api\Export\FileGeneratorInterface
{
    protected const FILENAME = '';
    protected const HEADER_ROW = ['searchterm', 'locale'];
    protected const DELIMITER = "\t\t";
    /**
     * @var \Aligent\FredhopperIndexer\Helper\SuggestConfig
     */
    protected $suggestConfig;
    /**
     * @var \Magento\Framework\Filesystem\Driver\File
     */
    protected $fileSystem;

    public function __construct(
        \Aligent\FredhopperIndexer\Helper\SuggestConfig $suggestConfig,
        \Magento\Framework\Filesystem\Driver\File $fileSystem
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

    protected function addRow(array $data) {
        $rowContent = '';
        foreach ($data as $column) {
            $rowContent .= $column . self::DELIMITER;
        }
        // remove trailing delimiter
        return rtrim($rowContent, self::DELIMITER);

    }

    protected abstract function getSearchTerms() : array;
}