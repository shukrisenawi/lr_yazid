<?php

namespace App\Support;

class TrustedProxyList
{
    /** @return array{addresses: list<string>, valid: bool} */
    public static function parse(string $input): array
    {
        $addresses = [];
        foreach (explode(',', $input) as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }
            [$ip, $prefix] = array_pad(explode('/', $entry, 2), 2, null);
            if (! filter_var($ip, FILTER_VALIDATE_IP)
                || ($prefix !== null && (! ctype_digit($prefix) || (int) $prefix < 1 || (int) $prefix > (str_contains($ip, ':') ? 128 : 32)))) {
                // Never turn malformed entries, hostnames, "*" or /0 into trust-all.
                return ['addresses' => [], 'valid' => false];
            }
            $addresses[] = $entry;
        }

        return ['addresses' => array_values(array_unique($addresses)), 'valid' => true];
    }
}
