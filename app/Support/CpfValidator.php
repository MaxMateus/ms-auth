<?php

namespace App\Support;

class CpfValidator
{
    public static function sanitize(?string $cpf): string
    {
        return preg_replace('/\D/', '', (string) $cpf);
    }

    public static function isValid(?string $cpf): bool
    {
        $cpf = self::sanitize($cpf);

        if (strlen($cpf) !== 11) {
            return false;
        }

        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;

            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }

            $d = ((10 * $d) % 11) % 10;

            if ((int) $cpf[$t] !== $d) {
                return false;
            }
        }

        return true;
    }
}
