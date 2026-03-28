<?php

declare(strict_types=1);

namespace App\Services;

use App\Lib\Dns\DnsProviderInterface;

class DnsManagementService
{
    public function __construct(private readonly DnsProviderInterface $dnsProvider)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecords(): array
    {
        return $this->dnsProvider->listRecords();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function createRecord(array $params): void
    {
        $this->dnsProvider->createRecord($params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function updateRecord(string $recordId, array $params): void
    {
        $this->dnsProvider->updateRecord($recordId, $params);
    }

    public function deleteRecord(string $recordId): void
    {
        $this->dnsProvider->deleteRecord($recordId);
    }
}
