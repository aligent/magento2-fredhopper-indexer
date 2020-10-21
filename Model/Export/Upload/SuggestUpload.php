<?php
namespace Aligent\FredhopperIndexer\Model\Export\Upload;

class SuggestUpload extends AbstractUpload
{

    protected function getFredhopperUploadEndpoint(): string
    {
        return self::SUGGEST_ENDPOINT;
    }
}
