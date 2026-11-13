<?php

namespace App\Support;

use Illuminate\Support\Str;

class Text
{
    public static function normalize(string $value): string
    {
        $value = Str::lower(Str::ascii($value));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }

    public static function tokens(string $value): array
    {
        $normalized = self::normalize($value);
        $normalized = preg_replace(['/(\d)([a-z])/', '/([a-z])(\d)/'], '$1 $2', $normalized) ?? $normalized;

        return $normalized === '' ? [] : explode(' ', $normalized);
    }
}
