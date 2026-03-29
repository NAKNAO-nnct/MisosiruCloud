<?php

declare(strict_types=1);

namespace App\Data\Dns;

use App\Models\DnsRecord;

final readonly class DnsRecordData
{
    private function __construct(
        private int $id,
        private int $dnsZoneId,
        private string $name,
        private string $type,
        private string $content,
        private int $ttl,
        private ?int $priority,
        private ?string $externalId,
        private ?string $comment,
    ) {
    }

    public static function of(DnsRecord $model): self
    {
        return new self(
            id: $model->id,
            dnsZoneId: (int) $model->dns_zone_id,
            name: (string) $model->name,
            type: (string) $model->type,
            content: (string) $model->content,
            ttl: (int) $model->ttl,
            priority: $model->priority,
            externalId: $model->external_id,
            comment: $model->comment,
        );
    }

    public static function make(array $attributes): self
    {
        $priority = $attributes['priority'] ?? null;

        return new self(
            id: (int) ($attributes['id'] ?? 0),
            dnsZoneId: (int) ($attributes['dns_zone_id'] ?? 0),
            name: (string) ($attributes['name'] ?? ''),
            type: (string) ($attributes['type'] ?? ''),
            content: (string) ($attributes['content'] ?? ''),
            ttl: (int) ($attributes['ttl'] ?? 300),
            priority: $priority !== null ? (int) $priority : null,
            externalId: isset($attributes['external_id']) ? (string) $attributes['external_id'] : null,
            comment: isset($attributes['comment']) ? (string) $attributes['comment'] : null,
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDnsZoneId(): int
    {
        return $this->dnsZoneId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function toArray(): array
    {
        return [
            'dns_zone_id' => $this->dnsZoneId,
            'name' => $this->name,
            'type' => $this->type,
            'content' => $this->content,
            'ttl' => $this->ttl,
            'priority' => $this->priority,
            'external_id' => $this->externalId,
            'comment' => $this->comment,
        ];
    }
}
