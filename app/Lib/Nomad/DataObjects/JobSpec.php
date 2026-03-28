<?php

declare(strict_types=1);

namespace App\Lib\Nomad\DataObjects;

readonly class JobSpec
{
    /**
     * @param array<int, string>               $datacenters
     * @param array<int, array<string, mixed>> $taskGroups
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $namespace,
        public string $type,
        public array $datacenters,
        public array $taskGroups,
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
            namespace: (string) ($data['Namespace'] ?? 'default'),
            type: (string) ($data['Type'] ?? 'service'),
            datacenters: array_values($data['Datacenters'] ?? []),
            taskGroups: array_values($data['TaskGroups'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ID' => $this->id,
            'Name' => $this->name,
            'Namespace' => $this->namespace,
            'Type' => $this->type,
            'Datacenters' => $this->datacenters,
            'TaskGroups' => $this->taskGroups,
        ];
    }
}
