<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export;

use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;

class FullExporter extends AbstractProductExporter
{

    /**
     * @return bool
     * @throws FileSystemException
     * @throws LocalizedException
     */
    public function export(): bool
    {
        $this->logger->info('Performing full product export');
        $success = $this->doExport(false);
        $this->logger->info('Full product export ' . ($success ? 'completed successfully' : 'failed'));
        return $success;
    }

    /**
     * @return string
     */
    protected function getDirectory(): string
    {
        if (!$this->directory) {
            $this->directory = '/tmp/fh_export_' . time();
        }
        return $this->directory;
    }

    /**
     * @return string
     */
    protected function getZipFileName(): string
    {
        return self::ZIP_FILE_FULL;
    }
}
