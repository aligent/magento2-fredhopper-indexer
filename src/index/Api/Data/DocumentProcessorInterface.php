<?php

declare(strict_types=1);

namespace Aligent\FredhopperIndexer\Api\Data;

interface DocumentProcessorInterface
{
    /**
     * Perform additional processing on documents
     *
     * @param array $documents
     * @param int $scopeId
     * @return array
     */
    public function processDocuments(array $documents, int $scopeId) : array;
}
