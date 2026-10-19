<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Sunrice\Cms\CmsServiceProvider;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Form;

use function Pest\Laravel\get;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
});

it('speaks the page\'s language on the site', function () {
    get('/search?q=kopi')->assertOk()->assertSee('<h1>Search</h1>', false)->assertSee('0 results for “kopi”');
    get('/id/search?q=kopi')->assertOk()->assertSee('<h1>Cari</h1>', false)->assertSee('0 hasil untuk “kopi”')->assertSee('<title>Cari: kopi', false);

    Blueprint::query()->create(['handle' => 'contact', 'title' => 'Contact', 'fields' => [['handle' => 'name', 'type' => 'text']]]);
    Form::query()->create(['handle' => 'contact', 'title' => 'Contact', 'blueprint' => 'contact']);
    app()->setLocale('id');
    expect(Blade::render('<x-sunrice-cms::form handle="contact" />'))->toContain('<button type="submit">Kirim</button>')
        ->and(Blade::render('<x-sunrice-cms::form handle="contact" submit="Go" />'))->toContain('<button type="submit">Go</button>')
        ->and(Form::query()->first()?->successMessage())->toBe('Terima kasih, pesan Anda sudah kami terima.');
});

it('publishes the plain pages as starter templates', function () {
    $paths = ServiceProvider::pathsToPublish(CmsServiceProvider::class, 'sunrice-cms-templates');

    expect(array_map(fn (string $to) => str_replace(resource_path(), '', $to), array_values($paths)))
        ->toBe(['/views/cms/entry.blade.php', '/views/cms/listing.blade.php', '/views/cms/term.blade.php', '/views/cms/search.blade.php'])
        ->and(array_filter(array_keys($paths), is_file(...)))->toHaveCount(4);
});
