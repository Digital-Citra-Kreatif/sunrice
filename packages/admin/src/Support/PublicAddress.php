<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

use Closure;

/**
 * Whether a host is out on the public internet, for anything that fetches
 * a URL somebody else chose (server-side request forgery). Every address
 * the host resolves to has to be a global unicast address: not this
 * server (loopback), its network (private, CGNAT, link-local, unique
 * local), multicast or broadcast, documentation or benchmark ranges, nor
 * an IPv6 form that wraps an IPv4 address (IPv4-mapped and -compatible,
 * NAT64, 6to4, Teredo), which could lead to any of those.
 *
 * Fetch the address `of()` returns (pin it, e.g. with `CURLOPT_RESOLVE`)
 * and don't follow redirects, so the address checked is the one
 * connected to.
 *
 * @api
 */
final class PublicAddress
{
    /** CIDR ranges refused on top of PHP's FILTER_FLAG_GLOBAL_RANGE. */
    private const BLOCKED = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12',
        '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
        '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
        '::/96', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/23', '2001:db8::/32',
        '2002::/16', '3fff::/20', '5f00::/16', 'fc00::/7', 'fe80::/10', 'fec0::/10', 'ff00::/8',
    ];

    /** @var (Closure(string): list<string>)|null */
    private static ?Closure $resolver = null;

    /**
     * The address to connect to when the host (a name, an IP address, or
     * an IPv6 address in brackets) and every address it resolves to are
     * public; null otherwise, or when it doesn't resolve.
     */
    public static function of(string $host): ?string
    {
        $host = trim($host, '[]');
        if ($host === '') {
            return null;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : self::resolve($host);
        foreach ($ips as $ip) {
            if (! self::isPublic($ip)) {
                return null;
            }
        }

        return $ips[0] ?? null;
    }

    /** Whether an IP address is a public (global unicast) one. */
    public static function isPublic(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }
        // An IPv4-mapped or -compatible IPv6 address is the IPv4 address it wraps (and both ranges are refused below).
        if (strlen($packed) === 16 && (str_starts_with($packed, str_repeat("\0", 10)."\xff\xff") || str_starts_with($packed, str_repeat("\0", 12)))) {
            $v4 = inet_ntop(substr($packed, 12));
            if ($v4 === false || ! self::isPublic($v4)) {
                return false;
            }
        }
        foreach (self::BLOCKED as $range) {
            if (self::inRange($packed, $range)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve host names with this instead of DNS (for tests); null goes
     * back to DNS.
     *
     * @param  (Closure(string): list<string>)|null  $resolver
     */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        return gethostbynamel($host) ?: [];
    }

    private static function inRange(string $packed, string $range): bool
    {
        [$network, $bits] = explode('/', $range);
        $net = inet_pton($network);
        if ($net === false || strlen($net) !== strlen($packed)) {
            return false;
        }
        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($packed, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($packed[$bytes]) & $mask) === (ord($net[$bytes]) & $mask);
    }
}
