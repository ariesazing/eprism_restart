<?php

namespace Tests\Unit\Similarity;

use App\Similarity\UrlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlGuardTest extends TestCase
{
    private function guard(array $hosts = []): UrlGuard
    {
        return new UrlGuard(fn (string $host) => $hosts[$host] ?? []);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/admin'],
            'localhost ipv6' => ['http://[::1]/'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'private 10/8' => ['http://10.0.0.5/'],
            'private 192.168/16' => ['https://192.168.1.10/'],
            'private 172.16/12' => ['http://172.16.0.1/'],
            'carrier-grade nat' => ['http://100.64.0.1/'],
            'ipv4-mapped ipv6 loopback' => ['http://[::ffff:127.0.0.1]/'],
            'unspecified' => ['http://0.0.0.0/'],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme' => ['ftp://example.com/file'],
            'javascript scheme' => ['javascript:alert(1)'],
            'credentials in url' => ['http://user:secret@example.com/'],
            'non-standard port' => ['http://example.com:6379/'],
            'no host' => ['http:///path'],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_it_refuses_urls_the_server_must_not_fetch(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->guard(['example.com' => ['93.184.216.34']])->inspect($url);
    }

    public function test_it_refuses_a_public_looking_name_that_resolves_to_an_internal_address(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->guard(['sneaky.example' => ['93.184.216.34', '10.0.0.7']])->inspect('https://sneaky.example/');
    }

    public function test_it_refuses_a_host_that_does_not_resolve(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->guard()->inspect('https://nowhere.example/');
    }

    public function test_it_accepts_a_public_host_and_returns_the_address_to_pin_the_connection_to(): void
    {
        $target = $this->guard(['example.com' => ['93.184.216.34']])->inspect('https://Example.com/some/page?q=1');

        $this->assertSame(['scheme' => 'https', 'host' => 'example.com', 'port' => 443, 'ip' => '93.184.216.34'], $target);
    }
}
