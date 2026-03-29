<?php

declare(strict_types=1);

namespace App\Http\Requests\Dns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDnsRecordDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['A', 'AAAA', 'CNAME', 'NS', 'TXT', 'MX', 'SRV'])],
            'content' => ['required', 'string', 'max:255'],
            'ttl' => ['required', 'integer', 'min:60', 'max:86400'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
