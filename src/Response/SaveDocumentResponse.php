<?php

namespace SimplyIT\Fattura24SDK\Response;

/**
 * SaveDocumentResponse
 *
 * Represents the response from Fattura24 API after saving a document.
 */
final readonly class SaveDocumentResponse
{
    public function __construct(
        public string $docId,
        public string $docNumber,
        public string $docType,
        public ?string $pdfUrl = null,
        public ?string $xmlUrl = null,
        public ?array $raw = null,
    ) {
    }

    /**
     * Creates response from raw API array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            docId: $data['docId'] ?? '',
            docNumber: $data['docNumber'] ?? '',
            docType: $data['docType'] ?? '',
            pdfUrl: $data['pdfUrl'] ?? null,
            xmlUrl: $data['xmlUrl'] ?? null,
            raw: $data['raw'] ?? null,
        );
    }

    /**
     * Checks if the document was successfully created.
     */
    public function isSuccess(): bool
    {
        return !empty($this->docId) && !empty($this->docNumber);
    }
}
