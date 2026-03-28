<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
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
        $tenantId = $this->route('tenant')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', "unique:tenants,slug,{$tenantId}", 'regex:/^[a-z0-9\-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'スラッグは小文字英数字とハイフンのみ使用できます。',
            'slug.unique' => 'そのスラッグは既に使用されています。',
        ];
    }
}
