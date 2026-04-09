<?php

namespace Davidakis\Fattura24SDK\Response;

/**
 * GetTemplatesResponse
 *
 * Response for getTemplates() API call.
 * Contains document templates organized by type (order, invoice).
 */
final readonly class GetTemplatesResponse
{
    /**
     * @param array<int, string> $order Order template ID => Name
     * @param array<int, string> $invoice Invoice template ID => Name
     */
    public function __construct(
        public array $order,
        public array $invoice,
    ) {
    }

    /**
     * Checks if there are any templates available.
     */
    public function isEmpty(): bool
    {
        return empty($this->order) && empty($this->invoice);
    }

    /**
     * Gets all templates regardless of type.
     *
     * @return array<int, string> Template ID => Name
     */
    public function getAllTemplates(): array
    {
        return \array_merge($this->order, $this->invoice);
    }

    /**
     * Finds a template by ID across all types.
     */
    public function findTemplateById(int $id): ?string
    {
        return $this->invoice[$id] ?? $this->order[$id] ?? null;
    }
}
