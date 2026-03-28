<?php

declare(strict_types=1);

namespace App\Http\Requests\Dbaas;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDatabaseRequest extends FormRequest
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
            'db_version' => ['sometimes', 'required', 'string', 'max:20'],
            'status' => ['sometimes', 'required', 'in:provisioning,running,stopped,error,upgrading'],
        ];
    }
}
