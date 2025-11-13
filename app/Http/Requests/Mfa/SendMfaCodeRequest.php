<?php

namespace App\Http\Requests\Mfa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMfaCodeRequest extends FormRequest
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
            'method' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'destination' => ['required', 'string', 'max:255'],
        ];
    }
}
