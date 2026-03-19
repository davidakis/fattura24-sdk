<?php

namespace SimplyIT\Fattura24SDK\Response;

final readonly class TestKeyResponse
{
    public function __construct(
        public int $returnCode,
        public string $description,
        public ?string $accountId = null,
        public ?string $emailOwner = null,
        public ?string $subscriptionType = null,
        public ?int $totalCallsLast24Hours = null,
        public ?string $expire = null
    ) {

    }

    public function isValid(): bool
    {
        return $this->returnCode === 1;
    }
}
