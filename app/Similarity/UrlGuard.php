<?php

namespace App\Similarity;

use Closure;
use InvalidArgumentException;

/**
 * Decides whether a URL is safe for the server itself to fetch. The web source downloads
 * pages whose addresses come from a third-party search engine — attacker-influenceable
 * input — so without this a crafted result could point the server at its own internal
 * network (cloud metadata endpoints, the Document Server, the database host, localhost
 * admin ports): server-side request forgery.
 *
 * A URL passes only if it is plain http(s) on a standard port, carries no credentials, and
 * *every* address its host resolves to is a public one. The resolved address is returned so
 * the caller can pin the connection to it (otherwise DNS could answer "public" here and
 * "internal" a moment later, when the HTTP client resolves the name itself).
 */
final class UrlGuard
{
    /**
     * @param  (Closure(string): list<string>)|null  $resolver  host => IP addresses; the system
     *                                                          resolver when null. Swapped out in tests.
     */
    public function __construct(private readonly ?Closure $resolver = null) {}

    /**
     * @return array{scheme: string, host: string, port: int, ip: ?string} `ip` is the IPv4
     *                                                                     address to pin the connection to, when the host has one
     *
     * @throws InvalidArgumentException when the URL must not be fetched
     */
    public function inspect(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http and https URLs can be fetched.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('URLs carrying credentials can\'t be fetched.');
        }

        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));

        if ($host === '') {
            throw new InvalidArgumentException('The URL has no host.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($port, [80, 443], true)) {
            throw new InvalidArgumentException('Only standard web ports can be fetched.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            throw new InvalidArgumentException('The host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! self::isPublic($address)) {
                throw new InvalidArgumentException('The host resolves to a non-public address.');
            }
        }

        $ipv4 = collect($addresses)->first(fn (string $address) => filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false);

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ip' => $ipv4];
    }

    public static function isPublic(string $address): bool
    {
        // An IPv4-mapped IPv6 address (::ffff:127.0.0.1) is the IPv4 address in disguise.
        if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $address, $mapped)) {
            $address = $mapped[1];
        }

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = ip2long($address);

            // Carrier-grade NAT (100.64.0.0/10) — not covered by PHP's private/reserved flags,
            // but just as internal.
            if ($long >= ip2long('100.64.0.0') && $long <= ip2long('100.127.255.255')) {
                return false;
            }
        }

        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return array_values(($this->resolver)($host));
        }

        $addresses = [];

        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $address = $record['ip'] ?? $record['ipv6'] ?? null;

            if ($address !== null) {
                $addresses[] = $address;
            }
        }

        if ($addresses === []) {
            $addresses = @gethostbynamel($host) ?: [];
        }

        return $addresses;
    }
}
