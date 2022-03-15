<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export;

class FullExporter extends AbstractProductExporter
{

    public function export(): bool
    {
        $this->logger->info('Performing full product export');
        $success = $this->doExport(false);
        $this->logger->info('Full product export ' . ($success ? 'completed successfully' : 'failed'));
        return $success;
    }

    protected function getDirectory(): string
    {
        if (!$this->directory) {
            $this->directory = '/tmp/fh_export_' . time();
        }
        return $this->directory;
    }

    protected function getZipFileName(): string
    {
        return self::ZIP_FILE_FULL;
    }
}
