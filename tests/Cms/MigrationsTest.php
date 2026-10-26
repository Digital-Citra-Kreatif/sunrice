<?php

declare(strict_types=1);

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Lookups the panel and the CMS make have an index (foreign keys too:
 * Postgres and SQLite don't index them by themselves), and site search
 * has its full-text index where the database has one.
 */
it('indexes what is looked up', function (string $table, array $columns) {
    $indexed = collect(Schema::getIndexes($table))->map(fn (array $index): array => $index['columns']);

    expect($indexed->contains(fn (array $on): bool => array_slice($on, 0, count($columns)) === $columns))
        ->toBeTrue("{$table} has no index starting with ".implode(', ', $columns).'.');
})->with([
    ['sunrice_revisions', ['entry_translation_id']],
    ['sunrice_media_usages', ['media_id']],
    ['sunrice_entry_term', ['entry_id']],
    ['sunrice_comments', ['parent_id']],
    ['sunrice_comments', ['author_email']],
    ['sunrice_comments', ['deleted_at']],
    ['sunrice_menu_items', ['entry_id']],
    ['sunrice_menu_items', ['term_id']],
    ['sunrice_menu_items', ['collection_id']],
    ['sunrice_entry_translations', ['status', 'published_at']],
    // The admin's entries lists in their default orders, and the most recently edited.
    ['sunrice_entry_translations', ['collection_id', 'locale', 'published_at']],
    ['sunrice_entry_translations', ['collection_id', 'locale', 'draft_title']],
    ['sunrice_entry_translations', ['locale', 'updated_at']],
    ['sunrice_entries', ['deleted_at']],
    ['sunrice_media', ['deleted_at']],
    ['sunrice_media', ['mime_type']],
    ['sunrice_terms', ['deleted_at']],
]);

it('has the full-text index for site search where the database has one', function () {
    $indexes = collect(Schema::getIndexes('sunrice_entry_translations'));
    match (DB::getDriverName()) {
        'mysql', 'mariadb' => expect($indexes->firstWhere('name', 'sunrice_entry_tr_search_fulltext'))->toMatchArray(['type' => 'fulltext', 'columns' => ['title', 'search_text']]),
        'pgsql' => expect($indexes->firstWhere('name', 'sunrice_entry_tr_search_index'))->not->toBeNull(),
        default => expect($indexes->firstWhere('name', 'sunrice_entry_tr_search_index'))->toBeNull(),
    };
});

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
        foreach ([...glob(dirname(__DIR__, 2).'/packages/admin/database/migrations/*.php') ?: [], ...glob(dirname(__DIR__, 2).'/packages/cms/database/migrations/*.php') ?: []] as $file) {
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
