<?php

declare(strict_types=1);
namespace Aligent\FredhopperExport\Cron;

use Aligent\FredhopperExport\Model\InvalidateExports;
use Aligent\FredhopperExport\Model\ResourceModel\Data\Export\CollectionFactory;

class UpdateInvalidExports
{
    /**
     * @param InvalidateExports $invalidateExports
     */
    public function __construct(
        private readonly InvalidateExports $invalidateExports,
    ) {
    }

    /**
     * Update the status of pending exports
     *
     * @return void
     */
    public function execute(): void
    {
        $this->invalidateExports->execute();
    }
}
