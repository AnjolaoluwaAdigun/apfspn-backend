<?php

namespace App\Support;

class CsvSanitizer
{
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true) ? "'".$value : $value;
    }

    public static function row(array $values): array
    {
        return array_map(self::cell(...), $values);
    }
}
