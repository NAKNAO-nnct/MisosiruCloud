<?php

declare(strict_types=1);

namespace App\Lib\Dns;

interface DnsProviderInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecords(): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function createRecord(array $params): array;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function updateRecord(string $recordId, array $params): array;

    public function deleteRecord(string $recordId): void;
}
