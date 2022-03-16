<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Model\Export\Upload;

class FasUpload extends AbstractUpload
{
    /**
     * @inheritDoc
     */
    protected function getFredhopperUploadEndpoint(): string
    {
        return self::FAS_ENDPOINT;
    }

    /**
     * @inheritDoc
     */
    protected function getFredhopperTriggerEndpoint(): string
    {
        return self::FAS_TRIGGER_ENDPOINT;
    }
}
