<?php

declare(strict_types=1);

namespace App\Data\Vm;

final readonly class VmDetailResponseData
{
    /**
     * @param array<string, mixed>|null $status
     */
    private function __construct(
        private ?VmMetaData $meta,
        private ?array $status,
        private ?string $node,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function make(array $attributes): self
    {
        $meta = $attributes['meta'] ?? null;

        return new self(
            meta: $meta instanceof VmMetaData
                ? $meta
                : (is_array($meta) ? VmMetaData::make($meta) : null),
            status: isset($attributes['status']) && is_array($attributes['status'])
                ? $attributes['status']
                : null,
            node: isset($attributes['node']) ? (string) $attributes['node'] : null,
        );
    }

    public function getMeta(): ?VmMetaData
    {
        return $this->meta;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStatus(): ?array
    {
        return $this->status;
    }

    public function getNode(): ?string
    {
        return $this->node;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'meta' => $this->meta,
            'status' => $this->status,
            'node' => $this->node,
        ];
    }
}
