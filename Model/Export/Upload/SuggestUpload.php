<?php
namespace Aligent\FredhopperIndexer\Model\Export\Upload;

class SuggestUpload extends AbstractUpload
{

    /**
     * @inheritDoc
     */
    protected function getFredhopperUploadEndpoint(): string
    {
        return self::SUGGEST_ENDPOINT;
    }

    /**
     * @inheritDoc
     */
    protected function getFredhopperTriggerEndpoint(): string
    {
        return self::SUGGEST_TRIGGER_ENDPOINT;
    }
}
