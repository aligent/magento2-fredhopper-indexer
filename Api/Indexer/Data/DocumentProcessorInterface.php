<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Api\Indexer\Data;

interface DocumentProcessorInterface
{
    /**
     * @param array $documents
     * @param int $scopeId
     */
    public function processDocuments(array &$documents, int $scopeId) : void;
}
