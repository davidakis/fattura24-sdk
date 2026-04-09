<?php

namespace Davidakis\Fattura24SDK\Response;

/**
 * GetChartOfAccountsResponse
 *
 * Response for getChartOfAccounts() API call.
 * Contains the chart of accounts (piano dei conti) entries.
 */
final readonly class GetChartOfAccountsResponse
{
    /**
     * @param array<int, string> $accounts Account ID => Description
     */
    public function __construct(
        public array $accounts,
    ) {
    }

    /**
     * Checks if chart of accounts is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->accounts);
    }

    /**
     * Gets the description for a specific account.
     */
    public function getDescription(int $accountId): ?string
    {
        return $this->accounts[$accountId] ?? null;
    }

    /**
     * Finds accounts matching a search term.
     *
     * @param string $search Search term (case-insensitive)
     * @return array<int, string> Matching accounts
     */
    public function search(string $search): array
    {
        $search = \strtolower($search);

        return \array_filter(
            $this->accounts,
            fn ($description) => \str_contains(\strtolower($description), $search)
        );
    }

    /**
     * Gets all account IDs.
     *
     * @return int[]
     */
    public function getAccountIds(): array
    {
        return \array_keys($this->accounts);
    }
}
