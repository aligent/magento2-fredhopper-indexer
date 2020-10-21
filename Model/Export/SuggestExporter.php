<?php
namespace Aligent\FredhopperIndexer\Model\Export;

class SuggestExporter implements \Aligent\FredhopperIndexer\Api\Export\ExporterInterface
{
    const ZIP_FILE_NAME = 'data.zip';
    /**
     * @var ZipFile
     */
    protected $zipFile;
    /**
     * @var Upload\SuggestUpload
     */
    protected $upload;
    /**
     * @var \Magento\Framework\Filesystem\Driver\File
     */
    protected $filesystem;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
    /**
     * @var \Aligent\FredhopperIndexer\Api\Export\FileGeneratorInterface[]
     */
    protected $fileGenerators;

    public function __construct(
        \Aligent\FredhopperIndexer\Model\Export\ZipFile $zipFile,
        \Aligent\FredhopperIndexer\Model\Export\Upload\SuggestUpload $upload,
        \Magento\Framework\Filesystem\Driver\File $filesystem,
        \Psr\Log\LoggerInterface $logger,
        array $fileGenerators = []
    ) {
        $this->fileGenerators = $fileGenerators;
        $this->zipFile = $zipFile;
        $this->filesystem = $filesystem;
        $this->upload = $upload;
        $this->logger = $logger;
    }

    public function export() : bool
    {
        $this->logger->info('Performing suggest export');
        $directory = '/tmp/fh_export_suggest_' . time();
        try {
            $this->filesystem->createDirectory($directory);
        } catch (\Exception $e) {
            $this->logger->critical(
                "Could not create directory {$directory} for export",
                ['exception' => $e]
            );
            return false;
        }
        $files = [];
        foreach ($this->fileGenerators as $fileGenerator) {
            $file = $fileGenerator->generateFile($directory);
            if (!empty($file)) {
                $files[] = $file;
            }
        }
        if (empty($files)) {
            $this->logger->info('Suggest export has no files to process - exiting.');
            return true;
        }

        $zipFilePath = $directory . DIRECTORY_SEPARATOR . self::ZIP_FILE_NAME;
        $success = $this->zipFile->createZipFile($zipFilePath, $files);
        if ($success) {
            $success = $this->upload->uploadZipFile($zipFilePath);
        }
        $this->logger->info('Suggest export '. ($success ? 'completed successfully' : 'failed'));
        return $success;
    }
}
