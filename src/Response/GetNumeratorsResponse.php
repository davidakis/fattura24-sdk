<?php

namespace SimplyIT\Fattura24SDK\Response;

/**
 * GetNumeratorsResponse
 *
 * Response for getNumerators() API call.
 * Contains document numerators (sezionali) organized by document type.
 */
final readonly class GetNumeratorsResponse
{
    /**
     * @param array<int, string> $invoice Invoice numerator ID => Label
     * @param array<int, string> $receipt Receipt numerator ID => Label
     * @param array<int, string> $electronicInvoice Electronic invoice numerator ID => Label
     */
    public function __construct(
        public array $invoice,
        public array $receipt,
        public array $electronicInvoice,
    ) {
    }

    /**
     * Finds the default numerator ID for a document type.
     * Searches for the label containing "(Predefinito)".
     *
     * @param string $type 'invoice', 'receipt', or 'electronic_invoice'
     * @return int|null Default numerator ID or null if not found
     */
    public function getDefaultId(string $type): ?int
    {
        $numerators = match ($type) {
            'invoice' => $this->invoice,
            'receipt' => $this->receipt,
            'electronic_invoice' => $this->electronicInvoice,
            default => [],
        };

        foreach ($numerators as $id => $label) {
            if (\str_contains($label, '(Predefinito)')) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * Gets the label for a specific numerator.
     *
     * @param string $type 'invoice', 'receipt', or 'electronic_invoice'
     * @param int $id Numerator ID
     */
    public function getLabel(string $type, int $id): ?string
    {
        return match ($type) {
            'invoice' => $this->invoice[$id] ?? null,
            'receipt' => $this->receipt[$id] ?? null,
            'electronic_invoice' => $this->electronicInvoice[$id] ?? null,
            default => null,
        };
    }

    /**
     * Gets all numerators regardless of type.
     *
     * @return array<int, string> Numerator ID => Label
     */
    public function getAllNumerators(): array
    {
        return \array_merge($this->invoice, $this->receipt, $this->electronicInvoice);
    }
}
