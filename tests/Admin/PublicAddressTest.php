<?php

declare(strict_types=1);

use Sunrice\Admin\Support\PublicAddress;

afterEach(fn () => PublicAddress::resolveUsing(null));

it('refuses addresses on this server, its network and special ranges', function (string $ip) {
    expect(PublicAddress::isPublic($ip))->toBeFalse()
        ->and(PublicAddress::of($ip))->toBeNull()
        ->and(PublicAddress::of("[{$ip}]"))->toBeNull();
})->with([
    // IPv4
    '0.0.0.0', '0.1.2.3', '10.0.0.1', '100.64.0.1', '100.127.255.254', '127.0.0.1', '127.8.8.8',
    '169.254.169.254', '172.16.0.1', '172.31.255.255', '192.0.0.1', '192.0.2.10', '192.168.1.1',
    '198.18.0.1', '198.51.100.1', '203.0.113.1', '224.0.0.1', '239.255.255.250', '240.0.0.1', '255.255.255.255',
    // IPv6
    '::', '::1', '::7f00:1', '::127.0.0.1',
    '::ffff:127.0.0.1', '::ffff:7f00:1', '::ffff:169.254.169.254', '::ffff:10.0.0.1', '::ffff:8.8.8.8', '::ffff:0:0',
    '64:ff9b::7f00:1', '64:ff9b::a9fe:a9fe', '64:ff9b::808:808', '64:ff9b:1::1',
    'fe80::1', 'fc00::1', 'fd12:3456::1', 'ff02::1', 'ff05::2', 'fec0::1',
    '2001:db8::1', '2002:7f00:1::', '2001::1', '100::1',
    // not addresses
    '', 'not an ip', '999.1.1.1',
]);

it('lets public addresses through', function (string $ip) {
    expect(PublicAddress::isPublic($ip))->toBeTrue()
        ->and(PublicAddress::of($ip))->toBe($ip)
        ->and(PublicAddress::of("[{$ip}]"))->toBe($ip);
})->with(['8.8.8.8', '93.184.216.34', '1.1.1.1', '100.63.255.255', '100.128.0.1', '2606:4700:4700::1111', '2a00:1450:4001::200e']);

it('checks every address a host name resolves to', function () {
    PublicAddress::resolveUsing(fn (string $host): array => match ($host) {
        'cdn.example' => ['93.184.216.34', '1.1.1.1'],
        'inside.example' => ['10.0.0.5'],
        'mixed.example' => ['93.184.216.34', '127.0.0.1'],
        'cgnat.example' => ['100.64.1.1'],
        default => [],
    });

    expect(PublicAddress::of('cdn.example'))->toBe('93.184.216.34')
        ->and(PublicAddress::of('inside.example'))->toBeNull()
        ->and(PublicAddress::of('mixed.example'))->toBeNull()
        ->and(PublicAddress::of('cgnat.example'))->toBeNull()
        ->and(PublicAddress::of('nowhere.example'))->toBeNull();
});
