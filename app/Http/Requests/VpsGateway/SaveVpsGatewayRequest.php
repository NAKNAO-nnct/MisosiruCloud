<?php

declare(strict_types=1);

namespace App\Http\Requests\VpsGateway;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVpsGatewayRequest extends FormRequest
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
        $id = $this->route('vpsGateway');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('vps_gateways', 'name')->ignore($id)],
            'global_ip' => ['required', 'ip', 'max:45', Rule::unique('vps_gateways', 'global_ip')->ignore($id)],
            'wireguard_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'wireguard_public_key' => ['required', 'string', 'min:44', 'max:64'],
            'status' => ['required', Rule::in(['active', 'maintenance', 'inactive'])],
            'purpose' => ['nullable', 'string', 'max:255'],
        ];
    }
}
