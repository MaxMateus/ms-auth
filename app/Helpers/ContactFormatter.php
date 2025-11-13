<?php

namespace App\Helpers;

/**
 * Helper responsável por normalizar contatos (e-mail e telefone).
 */
class ContactFormatter
{
    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function digitsOnly(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    public static function normalizePhone(string $value): string
    {
        $digits = self::digitsOnly($value);

        return str_starts_with($digits, '55') ? $digits : '55' . $digits;
    }
}
