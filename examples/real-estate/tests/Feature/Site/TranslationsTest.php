<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

it('has the same interface words in English and Indonesian', function (): void {
    $en = array_keys(Arr::dot(require lang_path('en/site.php')));
    $id = array_keys(Arr::dot(require lang_path('id/site.php')));

    expect(array_values(array_diff($en, $id)))->toBe([])
        ->and(array_values(array_diff($id, $en)))->toBe([]);
});

it('uses only interface words that exist', function (): void {
    $words = Arr::dot(require lang_path('en/site.php'));
    $used = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
        if ($file->isFile()) {
            preg_match_all("/(?:__|trans_choice)\('(site\.[a-z0-9_.\-]*[a-z0-9_\-])'/", (string) file_get_contents($file->getPathname()), $m);
            array_push($used, ...$m[1]);
        }
    }
    $missing = array_values(array_filter(array_unique($used), fn (string $key) => ! array_key_exists(substr($key, 5), $words)));

    expect($missing)->toBe([]);
});
