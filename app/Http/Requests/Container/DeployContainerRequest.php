<?php

declare(strict_types=1);

namespace App\Http\Requests\Container;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeployContainerRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'image' => ['required', 'string', 'max:500'],
            'replicas' => ['required', 'integer', 'min:1', 'max:20'],
            'cpu_mhz' => ['required', 'integer', 'min:100', 'max:32000'],
            'memory_mb' => ['required', 'integer', 'min:64', 'max:131072'],
            'domain' => ['nullable', 'string', 'max:255'],
            'port_mappings' => ['nullable', 'array'],
            'port_mappings.*.label' => ['required_with:port_mappings', 'string', 'max:30'],
            'port_mappings.*.to' => ['required_with:port_mappings', 'integer', 'min:1', 'max:65535'],
            'port_mappings.*.value' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'env_vars' => ['nullable', 'array'],
            'env_vars.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
