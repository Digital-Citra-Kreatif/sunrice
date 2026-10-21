<?php

declare(strict_types=1);

use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\get;

/*
 * Search results link to the language's own URL pattern (FULLTEXT on
 * MySQL: commitForFullText()).
 */
beforeEach(function () {
    commitForFullText();
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Blueprint::query()->create(['handle' => 'book', 'title' => 'Book', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'blueprint' => 'book', 'route' => '/books/{slug}']);
    $books->setOwnRoute('id', '/buku/{slug}')->save();
    $hello = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($books, ['title' => 'Coffee', 'data' => ['intro' => 'Fresh beans']]));
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($hello->entry, 'id'), ['title' => 'Kopi', 'slug' => 'kopi', 'data' => ['intro' => 'Biji segar']]));
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($books, ['title' => 'Tea', 'data' => ['intro' => 'Fresh leaves']]));
});

it('links search results to the language\'s own addresses', function () {
    get('/id/search?q=segar')->assertOk()->assertSee('<a href="'.url('/id/buku/kopi').'">Kopi</a>', false);
    get('/search?q=fresh')->assertOk()->assertSee('<a href="'.url('/books/coffee').'">Coffee</a>', false);
    // The main language's standing in, at the language's address.
    get('/id/search?q=leaves')->assertOk()->assertSee('<a href="'.url('/id/buku/tea').'">Tea</a>', false);
});
