<?php

declare(strict_types=1);

namespace App\Http\Requests\Network;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreNetworkRequest extends FormRequest
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
            'network_cidr' => ['required', 'string', 'max:50', 'regex:/^\d{1,3}(?:\.\d{1,3}){3}\/\d{1,2}$/'],
        ];
    }
}
