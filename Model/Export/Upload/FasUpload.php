<?php
namespace Aligent\FredhopperIndexer\Model\Export\Upload;

class FasUpload extends AbstractUpload
{

    protected function getFredhopperUploadEndpoint(): string
    {
        return self::FAS_ENDPOINT;
    }
}
