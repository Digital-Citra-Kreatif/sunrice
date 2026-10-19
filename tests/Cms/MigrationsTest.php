<?php

declare(strict_types=1);

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;

/*
 * MySQL refuses identifiers (index and key names) over 64 characters, and
 * Laravel's generated names grow with the table and columns. Compile the
 * migrations for MySQL without a server and check every name.
 */
it('keeps every index name within MySQL\'s 64 characters', function () {
    // A MySQL connection that never connects, made the default so migrations use it.
    $mysql = new MySqlConnection(fn () => throw new RuntimeException('No server needed.'), 'sunrice', '', ['name' => 'mysql-pretend', 'driver' => 'mysql', 'server_version' => '8.4.0']);
    app('db')->extend('mysql-pretend', fn () => $mysql);
    config(['database.connections.mysql-pretend' => ['driver' => 'mysql-pretend']]);
    $previous = DB::getDefaultConnection();
    DB::setDefaultConnection('mysql-pretend');

    $statements = [];
    try {
        foreach (glob(dirname(__DIR__, 2).'/packages/{admin,cms}/database/migrations/*.php', GLOB_BRACE) ?: [] as $file) {
            $migration = require $file;
            foreach ($mysql->pretend(fn () => $migration->up()) as $query) {
                $statements[] = $query['query'];
            }
        }
    } finally {
        DB::setDefaultConnection($previous);
    }

    preg_match_all('/(?:index|unique|constraint|key) `([^`]+)`/i', implode("\n", $statements), $names);
    expect($names[1])->not->toBeEmpty();
    foreach ($names[1] as $name) {
        expect(strlen($name))->toBeLessThanOrEqual(64, "\"{$name}\" is longer than MySQL allows; name it in the migration.");
    }
});
