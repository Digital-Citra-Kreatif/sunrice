<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\CollectionListing;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Models\TermTranslation;
use Sunrice\Cms\Settings\SiteSettings;
use Sunrice\Cms\Structure\ImportReport;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Structure\StructureImportFailed;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\artisan;

/** The entry of a collection by its main slug. */
function entryBySlug(string $slug): ?Entry
{
    return EntryTranslation::query()->where('draft_slug', $slug)->first()?->entry;
}

it('stops before importing anything when the file has texts in a language the site lacks, or leaves them out when asked', function () {
    $file = ['sunrice' => 1, 'locales' => ['en', 'id'],
        'blueprints' => [['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'intro', 'type' => 'text', 'translations' => ['id' => ['label' => 'Pembuka']]]]]],
        'collections' => [['handle' => 'journal', 'title' => 'Journal', 'blueprint' => 'page', 'listing_route' => '/journal', 'listings' => ['en' => ['intro' => 'News'], 'id' => ['intro' => 'Berita']], 'translations' => ['id' => ['title' => 'Jurnal']]]],
    ];

    // The fresh install the report came from: English only.
    expect(fn () => app(StructureImporter::class)->import($file))->toThrow(StructureImportFailed::class,
        "Can't import the file: It has texts in ".Locales::displayName('id')." (id), which isn't one of this site's languages (en). Add it in Settings → Languages or set SUNRICE_LOCALES=en,id, then import again");
    expect(fn () => app(StructureImporter::class)->import($file, dryRun: true))->toThrow(StructureImportFailed::class, Locales::displayName('id').' (id)');
    expect(Blueprint::query()->count())->toBe(0)->and(Collection::query()->count())->toBe(0);
    file_put_contents($path = sys_get_temp_dir().'/structure-'.uniqid().'.json', json_encode($file));
    artisan('sunrice:import-structure', ['file' => $path])->expectsOutputToContain(Locales::displayName('id').' (id)')->assertFailed();
    artisan('sunrice:import-structure', ['file' => $path, '--skip-other-languages' => true, '--dry-run' => true])->expectsOutputToContain('Left out: the texts in id')->assertSuccessful();

    // Left out when asked: the English is imported, and the report says what was skipped.
    $report = app(StructureImporter::class)->import($file, skipOtherLanguages: true);
    expect($report->status('collection', 'journal'))->toBe(ImportReport::CREATED)
        ->and($report->items()[0]['notes'][0])->toContain('Left out: the texts in id')
        ->and(Collection::query()->where('handle', 'journal')->first()?->translations)->toBe([])
        ->and(CollectionListing::query()->pluck('locale')->all())->toBe(['en'])
        ->and(Blueprint::query()->where('handle', 'page')->first()?->fields[0])->not->toHaveKey('translations.id');

    // With the language on the site, all of it.
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Collection::query()->forceDelete();
    app(StructureImporter::class)->import($file);
    expect(Collection::query()->where('handle', 'journal')->sole()->listings()->orderBy('locale')->pluck('locale')->all())->toBe(['en', 'id']);
});

it('round-trips content into an empty site: entries, terms and files by name, at any depth, links in a loop, missing files left empty', function () {
    Storage::fake('public');
    config(['sunrice-admin.media.queue' => false]);
    Blueprint::query()->create(['handle' => 'agent', 'title' => 'Agent', 'fields' => [['handle' => 'phone', 'type' => 'text']]]);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'agent', 'type' => 'entries', 'required' => true, 'config' => ['collections' => ['agents']]],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
        ['handle' => 'photo', 'type' => 'media'],
        ['handle' => 'gone', 'type' => 'media'],
        ['handle' => 'more', 'type' => 'link'],
        ['handle' => 'facts', 'type' => 'repeater', 'fields' => [['handle' => 'label', 'type' => 'text'], ['handle' => 'see', 'type' => 'entries', 'config' => ['collections' => ['posts'], 'multiple' => true]]]],
    ]]);
    $agents = Collection::query()->create(['handle' => 'agents', 'title' => 'Agents', 'blueprint' => 'agent', 'route' => '/team/{slug}']);
    $posts = Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'blueprint' => 'post', 'route' => '/posts/{slug}']);
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics']);
    $news = app(SaveTerm::class)->handle($topics, ['title' => 'News']);
    $photo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('pool.jpg', 10, 10));
    $gone = app(UploadMedia::class)->handle(UploadedFile::fake()->image('gone.jpg', 10, 10));
    $ayu = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($agents, ['title' => 'Ayu Pratiwi', 'data' => ['phone' => '1']]));
    // "second" comes first in the file but links to "hello", which links back to it.
    $second = app(CreateEntry::class)->handle($posts, ['title' => 'Second', 'data' => ['agent' => $ayu->entry_id]]);
    $hello = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($posts, ['title' => 'Hello', 'data' => [
        'agent' => $ayu->entry_id, 'topics' => [$news->id], 'photo' => $photo->id, 'gone' => $gone->id,
        'more' => ['type' => 'entry', 'entry_id' => $second->entry_id],
        'facts' => [['_id' => 'r1', 'label' => 'See also', 'see' => [$second->entry_id]]],
    ], 'seo' => ['image' => $photo->id]]));
    app(SaveDraft::class)->handle($second->fresh(), ['data' => ['agent' => $ayu->entry_id, 'facts' => [['_id' => 'r2', 'label' => 'Back', 'see' => [$hello->entry_id]]]]]);

    $file = json_decode(json_encode(app(StructureExporter::class)->export(content: true), JSON_THROW_ON_ERROR), true);
    $exported = collect($file['entries'])->firstWhere('slug', 'hello')['translations']['en']['data'];
    expect($exported['agent'])->toBe(['entry' => 'agents/ayu-pratiwi'])
        ->and($exported['topics'])->toBe([['term' => 'topics/news']])
        ->and($exported['photo'])->toBe(['media' => $photo->path])
        ->and($exported['more']['entry_id'])->toBe(['entry' => 'posts/second'])
        ->and($exported['facts'][0]['see'])->toBe([['entry' => 'posts/second']])
        ->and($exported['seo']['image'])->toBe(['media' => $photo->path]);
    // The linking entry first, as a file may have it.
    $file['entries'] = collect($file['entries'])->sortBy(fn (array $entry) => $entry['slug'] === 'hello' ? 0 : 1)->values()->all();

    // Another site: no entries, terms or files of its own, and one of the files uploaded again (another path).
    Entry::withTrashed()->get()->each->forceDelete();
    Term::withTrashed()->get()->each->forceDelete();
    EntryTranslation::query()->delete();
    TermTranslation::query()->delete();
    Media::withTrashed()->get()->each->forceDelete();
    $again = app(UploadMedia::class)->handle(UploadedFile::fake()->image('pool.jpg', 10, 10));

    $report = app(StructureImporter::class)->import($file);

    $hello = entryBySlug('hello');
    $second = entryBySlug('second');
    $ayu = entryBySlug('ayu-pratiwi');
    $data = $hello->mainTranslation()->draft_data;
    expect($data['agent'])->toBe($ayu->id)
        ->and($data['topics'])->toBe([Term::query()->sole()->id])
        ->and($data['photo'])->toBe($again->id)
        ->and($data['gone'])->toBeNull()
        ->and($data['more']['entry_id'])->toBe($second->id)
        ->and($data['facts'][0]['see'])->toBe([$second->id])
        ->and($data['seo']['image'])->toBe($again->id)
        ->and($second->mainTranslation()->draft_data['facts'][0]['see'])->toBe([$hello->id])
        ->and($hello->mainTranslation()->isLive())->toBeTrue()
        ->and(collect($report->items())->firstWhere('handle', 'posts/hello')['notes'])->toBe(['Left empty (not on this site): media '.$gone->path.'.']);

    // Files with plain ids still import where those ids are the same records (the site they came from).
    $plain = ['sunrice' => 1, 'entries' => [['collection' => 'posts', 'slug' => 'third', 'translations' => ['en' => ['title' => 'Third', 'slug' => 'third', 'data' => ['agent' => $ayu->id], 'published' => false]]]]];
    app(StructureImporter::class)->import($plain);
    expect(entryBySlug('third')->mainTranslation()->draft_data['agent'])->toBe($ayu->id);
});

it('exports what the site shows as the content, and changes not published yet as a draft that stays a draft on import', function () {
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    $about = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($pages, ['title' => 'About', 'data' => ['intro' => 'Live words']]));
    app(SaveDraft::class)->handle($about->fresh(), ['title' => 'About us (draft)', 'data' => ['intro' => 'Unfinished words']]);
    app(CreateEntry::class)->handle($pages, ['title' => 'Coming', 'data' => ['intro' => 'Not yet']]);

    $file = json_decode(json_encode(app(StructureExporter::class)->export(content: true), JSON_THROW_ON_ERROR), true);
    $exported = collect($file['entries'])->firstWhere('slug', 'about')['translations']['en'];
    expect($exported['title'])->toBe('About')
        ->and($exported['data']['intro'])->toBe('Live words')
        ->and($exported['published'])->toBeTrue()
        ->and($exported['draft'])->toBe(['title' => 'About us (draft)', 'slug' => 'about', 'data' => ['intro' => 'Unfinished words']]);
    $coming = collect($file['entries'])->firstWhere('slug', 'coming')['translations']['en'];
    expect($coming['published'])->toBeFalse()->and($coming['data']['intro'])->toBe('Not yet')->and($coming)->not->toHaveKey('draft');

    Entry::withTrashed()->get()->each->forceDelete();
    EntryTranslation::query()->delete();
    app(StructureImporter::class)->import($file);

    $about = entryBySlug('about')->mainTranslation();
    expect($about->isLive())->toBeTrue()
        ->and($about->title)->toBe('About')
        ->and($about->data['intro'] ?? null)->toBe('Live words')
        ->and($about->draft_title)->toBe('About us (draft)')
        ->and($about->draft_data['intro'] ?? null)->toBe('Unfinished words');
    $this->get('/about')->assertOk()->assertSee('Live words')->assertDontSee('Unfinished words');
    expect(entryBySlug('coming')->mainTranslation()->isPublished())->toBeFalse();

    // Importing it again changes nothing.
    $report = app(StructureImporter::class)->import($file);
    expect($report->status('entry', 'pages/about'))->toBe(ImportReport::UNCHANGED)->and(Entry::query()->count())->toBe(2);
});

it('carries Settings → Site with --settings, the homepage and images portably, setting only what the other site left as it was', function () {
    Storage::fake('public');
    config(['sunrice-admin.media.queue' => false]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [['handle' => 'intro', 'type' => 'text']]]);
    $pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    $home = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($pages, ['title' => 'Home']));
    $logo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png', 10, 10));
    app(SaveSettings::class)->handle(new SiteSettings, ['name' => 'Acme', 'description' => 'Homes in Bali.', 'logo' => $logo->id, 'homepage' => $home->entry_id, 'twitter' => '@acme', 'noindex' => true,
        // Settings → SEO's structured data, stored with the site's.
        'publisherType' => 'Brewery', 'telephone' => '+62 21 555 0100', 'sameAs' => 'https://instagram.com/acme']);

    expect(app(StructureExporter::class)->export(content: true))->not->toHaveKey('settings');
    $file = json_decode(json_encode(app(StructureExporter::class)->export(content: true, settings: true), JSON_THROW_ON_ERROR), true);
    expect($file['settings']['site'])->toMatchArray(['name' => 'Acme', 'homepage' => ['entry' => 'pages/home'], 'logo' => ['media' => $logo->path], 'image' => null, 'twitter' => '@acme',
        'structuredData' => true, 'publisherType' => 'Brewery', 'telephone' => '+62 21 555 0100', 'sameAs' => 'https://instagram.com/acme'])
        ->and($file['settings']['site'])->not->toHaveKey('noindex');

    // Another site, which named itself already.
    Entry::withTrashed()->get()->each->forceDelete();
    EntryTranslation::query()->delete();
    (new SiteSettings)->fill(['name' => 'Ours', 'description' => '', 'logo' => null, 'homepage' => null, 'twitter' => '', 'noindex' => false,
        'publisherType' => '', 'telephone' => '', 'sameAs' => ''])->save();

    $dry = app(StructureImporter::class)->import($file, dryRun: true);
    expect($dry->status('settings', 'site'))->toBe(ImportReport::UPDATED)->and((new SiteSettings)->description)->toBe('');

    $report = app(StructureImporter::class)->import($file);
    $site = new SiteSettings;
    expect($site->name)->toBe('Ours')
        ->and($site->description)->toBe('Homes in Bali.')
        ->and($site->logo)->toBe($logo->id)
        ->and($site->homepage)->toBe(entryBySlug('home')?->id)
        ->and($site->noindex)->toBeFalse()
        ->and([$site->publisherType, $site->telephone, $site->profiles()])->toBe(['Brewery', '+62 21 555 0100', ['https://instagram.com/acme']])
        ->and(collect($report->items())->firstWhere('type', 'settings')['notes'])->toBe(['Keeps this site\'s own: name.']);
    expect(app(StructureImporter::class)->import($file)->status('settings', 'site'))->toBe(ImportReport::UNCHANGED);
    // Checked as Settings → SEO checks them.
    (new SiteSettings)->fill(['publisherType' => ''])->save();
    expect(fn () => app(StructureImporter::class)->import([...$file, 'settings' => ['site' => ['publisherType' => 'not a type!']]]))->toThrow(StructureImportFailed::class);

    unset($file['settings']['site']);
    $file['settings']['mail'] = ['host' => 'x'];
    expect(fn () => app(StructureImporter::class)->import($file))->toThrow(StructureImportFailed::class, 'settings.site');
});

it('counts files to come as in the library on a dry run, so content that requires them checks before they are uploaded', function () {
    $file = ['sunrice' => 1,
        'blueprints' => [['handle' => 'photo', 'title' => 'Photo', 'fields' => [['handle' => 'image', 'type' => 'media', 'required' => true, 'config' => ['types' => ['image']]]]]],
        'collections' => [['handle' => 'photos', 'title' => 'Photos', 'blueprint' => 'photo', 'route' => '/photos/{slug}']],
        'entries' => [['collection' => 'photos', 'slug' => 'sunrise', 'translations' => ['en' => ['title' => 'Sunrise', 'slug' => 'sunrise', 'data' => ['image' => ['media' => 'sun.png']]]]]],
    ];
    $png = sys_get_temp_dir().'/sun-'.uniqid().'.png';
    imagepng(imagecreatetruecolor(4, 3), $png);
    $text = sys_get_temp_dir().'/sun-'.uniqid().'.txt';
    file_put_contents($text, 'not an image');

    // Without it the required image is missing; with it the check passes and saves nothing, stand-ins included.
    expect(fn () => app(StructureImporter::class)->import($file, dryRun: true))->toThrow(StructureImportFailed::class, 'The Image field is required.');
    $report = app(StructureImporter::class)->import($file, dryRun: true, mediaToCome: ['sun.png' => $png]);
    expect($report->status('entry', 'photos/sunrise'))->toBe(ImportReport::CREATED)
        ->and(Media::query()->count())->toBe(0)
        ->and(Collection::query()->count())->toBe(0);
    // A file to come of the wrong kind is no image; other names aren't found; and a real import needs the files uploaded.
    expect(fn () => app(StructureImporter::class)->import($file, dryRun: true, mediaToCome: ['sun.png' => $text]))->toThrow(StructureImportFailed::class)
        ->and(fn () => app(StructureImporter::class)->import($file, dryRun: true, mediaToCome: ['moon.png' => $png]))->toThrow(StructureImportFailed::class, 'The Image field is required.')
        ->and(fn () => app(StructureImporter::class)->import($file, mediaToCome: ['sun.png' => $png]))->toThrow(InvalidArgumentException::class);
    unlink($png);
    unlink($text);
});
