<?php

namespace App\Support;

class Cnpj
{
    public static function digits(?string $value): string
    {
        return preg_replace('/\D/', '', $value ?? '') ?? '';
    }

    public static function isValid(?string $value): bool
    {
        $cnpj = self::digits($value);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        foreach ([12, 13] as $length) {
            $weights = $length === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $sum = 0;
            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $cnpj[$i] * $weights[$i];
            }
            $digit = $sum % 11 < 2 ? 0 : 11 - $sum % 11;

            if ((int) $cnpj[$length] !== $digit) {
                return false;
            }
        }

        return true;
    }
}
