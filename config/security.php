<?php

return [
    // Comma-separated explicit IPs/CIDRs of reverse proxies that strip untrusted
    // forwarding headers. Blank means no proxy trust; wildcards and /0 are rejected.
    'trusted_proxies' => env('TRUSTED_PROXIES', ''),
];
