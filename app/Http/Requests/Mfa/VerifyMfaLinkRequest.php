<?php

namespace App\Http\Requests\Mfa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyMfaLinkRequest extends FormRequest
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
            'code' => ['required', 'digits:6'],
        ];
    }
}
