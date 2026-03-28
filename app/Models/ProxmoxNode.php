<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'hostname', 'api_token_id',
    'api_token_secret_encrypted', 'snippet_api_url', 'snippet_api_token_encrypted', 'is_active',
])]
class ProxmoxNode extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'api_token_secret_encrypted' => 'encrypted',
            'snippet_api_token_encrypted' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
