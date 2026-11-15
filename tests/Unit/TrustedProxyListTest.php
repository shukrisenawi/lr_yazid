<?php

namespace Tests\Unit;

use App\Support\TrustedProxyList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrustedProxyListTest extends TestCase
{
    public function test_explicit_ips_and_bounded_cidrs_are_supported_and_deduplicated(): void
    {
        $this->assertSame(['addresses' => ['127.0.0.1', '10.0.0.0/8', '::1', '2001:db8::/32'], 'valid' => true],
            TrustedProxyList::parse('127.0.0.1, 10.0.0.0/8,::1,2001:db8::/32,127.0.0.1'));
        $this->assertSame(['addresses' => [], 'valid' => true], TrustedProxyList::parse(' , '));
    }

    public static function unsafeLists(): array
    {
        return array_map(fn ($value) => [$value], ['*', '0.0.0.0/0', '::/0', 'REMOTE_ADDR', 'proxy.example.com', '127.0.0.1:8000',
            '10.0.0.0/33', '2001:db8::/129', '127.0.0.1, *', '127.1', '10.0.0.1/abc']);
    }

    #[DataProvider('unsafeLists')]
    public function test_invalid_configuration_fails_closed(string $value): void
    {
        $this->assertSame(['addresses' => [], 'valid' => false], TrustedProxyList::parse($value));
    }
}
