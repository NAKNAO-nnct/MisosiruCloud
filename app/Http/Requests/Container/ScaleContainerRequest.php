<?php

declare(strict_types=1);

namespace App\Http\Requests\Container;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ScaleContainerRequest extends FormRequest
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
            'replicas' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
