<?php

declare(strict_types=1);
namespace Aligent\FredhopperIndexer\Model\Export\Data;

use Aligent\FredhopperIndexer\Model\ResourceModel\Export\CurrentIds;

class GetCurrentExportedVersion
{
    /**
     * @param CurrentIds $currentIds
     */
    public function __construct(
        private readonly CurrentIds $currentIds
    ) {
    }

    /**
     * Get the version ID associated with the current data set in Fredhopper
     *
     * @return int
     */
    public function execute(): int
    {
        $currentIds = $this->currentIds->getCurrentIds();
        return (int)($currentIds['version_id'] ?? 0);
    }
}
