<?php

namespace App\Support;

class CsvCell
{
    public static function safe(string $value): string
    {
        return preg_match('/^[\s\p{C}]*[=+\-@]|^[\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
