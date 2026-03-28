<?php

declare(strict_types=1);

namespace App\Http\Requests\ProxmoxNode;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProxmoxNodeRequest extends FormRequest
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
        $id = $this->route('proxmoxNode');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('proxmox_nodes', 'name')->ignore($id)],
            'hostname' => ['required', 'string', 'max:255'],
            'api_token_id' => ['required', 'string', 'max:255'],
            'api_token_secret' => [$id ? 'nullable' : 'required', 'string', 'max:255'],
            'snippet_api_url' => ['required', 'string', 'url', 'max:255'],
            'snippet_api_token' => [$id ? 'nullable' : 'required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'api_token_secret.required' => 'API トークンシークレットは必須です。',
            'snippet_api_token.required' => 'スニペット API トークンは必須です。',
        ];
    }
}
