<?php

declare(strict_types=1);

namespace App\Http\Requests\Vm;

use Illuminate\Foundation\Http\FormRequest;

class CreateVmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'label' => ['required', 'string', 'max:255'],
            'cpu' => ['nullable', 'integer', 'min:1', 'max:64'],
            'memory_mb' => ['nullable', 'integer', 'min:512'],
            'disk_gb' => ['nullable', 'integer', 'min:1'],
            'template_vmid' => ['required', 'integer'],
            'node' => ['required', 'string', 'max:255'],
            'new_vmid' => ['required', 'integer'],
            'purpose' => ['nullable', 'string', 'max:255'],
        ];
    }
}
