<?php

namespace App\Rules;

use App\Support\CpfValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!CpfValidator::isValid($value)) {
            $fail('O CPF informado não é válido.');
        }
    }
}
