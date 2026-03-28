<?php

declare(strict_types=1);

namespace App\Http\Requests\Dbaas;

use App\Enums\DatabaseType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CreateDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, array<mixed>|string|ValidationRule>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'db_type' => ['required', new Enum(DatabaseType::class)],
            'db_version' => ['required', 'string', 'max:20'],
            'label' => ['nullable', 'string', 'max:255'],
            'template_vmid' => ['required', 'integer', 'min:100'],
            'node' => ['required', 'string', 'max:50'],
            'new_vmid' => ['required', 'integer', 'min:100'],
            'cpu' => ['required', 'integer', 'min:1', 'max:64'],
            'memory_mb' => ['required', 'integer', 'min:512'],
            'disk_gb' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ];
    }
}
