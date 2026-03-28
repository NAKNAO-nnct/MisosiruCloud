<?php

declare(strict_types=1);

namespace App\Http\Requests\Dns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDnsRecordRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['A', 'AAAA', 'CNAME', 'TXT'])],
            'value' => ['required', 'string', 'max:1000'],
            'ttl' => ['required', 'integer', 'min:60', 'max:86400'],
        ];
    }
}
