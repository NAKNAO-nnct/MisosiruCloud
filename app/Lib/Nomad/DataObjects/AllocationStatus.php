<?php

declare(strict_types=1);

namespace App\Lib\Nomad\DataObjects;

readonly class AllocationStatus
{
    /**
     * @param array<string, mixed> $taskStates
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $jobId,
        public string $clientStatus,
        public string $desiredStatus,
        public array $taskStates,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function from(array $data): self
    {
        return new self(
            id: (string) ($data['ID'] ?? ''),
            name: (string) ($data['Name'] ?? ''),
            jobId: (string) ($data['JobID'] ?? ''),
            clientStatus: (string) ($data['ClientStatus'] ?? 'unknown'),
            desiredStatus: (string) ($data['DesiredStatus'] ?? 'unknown'),
            taskStates: is_array($data['TaskStates'] ?? null) ? $data['TaskStates'] : [],
        );
    }
}
