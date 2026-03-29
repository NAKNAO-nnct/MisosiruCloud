<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'dns_zone_id', 'name', 'type', 'content', 'ttl', 'priority', 'external_id', 'comment',
])]
class DnsRecord extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'dns_zone_id' => 'integer',
            'ttl' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DnsZone::class, 'dns_zone_id');
    }
}
