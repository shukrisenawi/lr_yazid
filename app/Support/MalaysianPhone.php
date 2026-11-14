<?php

namespace App\Support;

class MalaysianPhone
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }

        return '+'.$digits;
    }
}
