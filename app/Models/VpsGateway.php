<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'global_ip', 'wireguard_ip', 'wireguard_port',
    'wireguard_public_key', 'transit_wireguard_port', 'status', 'purpose', 'metadata',
])]
class VpsGateway extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
