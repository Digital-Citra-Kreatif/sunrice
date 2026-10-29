<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\TrashTerm;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\FieldTypes;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Reference;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'route' => '/{slug}']);
    $this->news = Collection::query()->create(['handle' => 'news', 'title' => 'News', 'route' => '/news/{slug}']);
});

function linkTarget(Collection $collection, string $title, bool $publish = true): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection, ['title' => $title]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

function linkErrors(Schema $schema, array $data): array
{
    return Validator::make(['data' => $data], $schema->rules($data))->errors()->keys();
}

it('links to an entry of the chosen collections', function () {
    $about = linkTarget($this->pages, 'About');
    $story = linkTarget($this->news, 'Story');
    $gone = linkTarget($this->pages, 'Gone');
    $gone->entry->delete();
    $schema = Schema::make([Field::make('cta', 'link', ['collections' => ['pages']])]);
    $any = Schema::make([Field::make('cta', 'link')]);

    expect(linkErrors($schema, ['cta' => ['type' => 'entry', 'entry_id' => $about->entry_id]]))->toBe([])
        ->and(linkErrors($schema, ['cta' => ['type' => 'entry', 'entry_id' => $story->entry_id]]))->toBe(['data.cta.entry_id'])
        ->and(linkErrors($schema, ['cta' => ['type' => 'entry', 'entry_id' => $gone->entry_id]]))->toBe(['data.cta.entry_id'])
        ->and(linkErrors($any, ['cta' => ['type' => 'entry', 'entry_id' => $story->entry_id]]))->toBe([])
        ->and(linkErrors($any, ['cta' => ['type' => 'page', 'url' => '/a']]))->toBe(['data.cta.type'])
        ->and(linkErrors($any, ['cta' => ['type' => 'entry', 'entry_id' => $story->entry_id, 'script' => 'x']]))->toBe(['data.cta'])
        ->and(linkErrors(Schema::make([new Field('cta', 'link', required: true)]), ['cta' => ['type' => 'entry']]))->toContain('data.cta.entry_id');
});

it('stores entry links by id and addresses as typed', function () {
    $schema = Schema::make([Field::make('cta', 'link')]);

    expect($schema->normalize(['cta' => ['type' => 'entry', 'entry_id' => '7', 'url' => '/ignored', 'label' => '', 'new_tab' => '1']]))
        ->toBe(['cta' => ['type' => 'entry', 'entry_id' => 7, 'label' => null, 'new_tab' => true]])
        ->and($schema->normalize(['cta' => ['url' => ' /about ', 'label' => 'About']]))
        ->toBe(['cta' => ['type' => 'url', 'url' => '/about', 'label' => 'About', 'new_tab' => false]])
        ->and($schema->normalize(['cta' => ['type' => 'entry']]))->toBe(['cta' => null])
        ->and($schema->references(['cta' => ['type' => 'entry', 'entry_id' => 7]]))->toEqual([new Reference('entry', 7, 'cta')]);
});

it('resolves an entry link\'s URL and title in the page\'s language', function () {
    $about = linkTarget($this->pages, 'About');
    $tentang = app(CreateTranslation::class)->handle($about->entry, 'id');
    app(SaveDraft::class)->handle($tentang, ['title' => 'Tentang', 'slug' => 'tentang']);
    app(PublishEntry::class)->handle($tentang);
    $draft = linkTarget($this->pages, 'Soon', publish: false);
    $schema = Schema::make([Field::make('cta', 'link'), Field::make('later', 'link')]);
    $data = $schema->normalize([
        'cta' => ['type' => 'entry', 'entry_id' => $about->entry_id, 'label' => 'Read more'],
        'later' => ['type' => 'entry', 'entry_id' => $draft->entry_id],
    ]);

    expect($schema->hydrate($data, new HydrationContext('en')))->toBe([
        'cta' => ['type' => 'entry', 'url' => url('/about'), 'label' => 'Read more', 'title' => 'About', 'new_tab' => false, 'rel' => null],
        'later' => ['type' => 'entry', 'url' => null, 'label' => null, 'title' => null, 'new_tab' => false, 'rel' => null],
    ])
        ->and($schema->hydrate($data, new HydrationContext('id'))['cta'])->toMatchArray(['url' => url('/id/tentang'), 'title' => 'Tentang'])
        // A preview shows a draft's title, but there's no page to link to yet.
        ->and($schema->hydrate($data, new HydrationContext('en', preview: true))['later'])->toMatchArray(['url' => null, 'title' => 'Soon']);
});

it('follows the entry when its slug changes', function () {
    $about = linkTarget($this->pages, 'About');
    $schema = Schema::make([Field::make('cta', 'link')]);
    $data = $schema->normalize(['cta' => ['type' => 'entry', 'entry_id' => $about->entry_id]]);
    app(SaveDraft::class)->handle($about->refresh(), ['title' => 'About us', 'slug' => 'about-us']);
    app(PublishEntry::class)->handle($about->refresh());

    expect($schema->hydrate($data, new HydrationContext('en'))['cta']['url'])->toBe(url('/about-us'));
});

it('offers the collections setting and sends it to the editor', function () {
    $type = app(FieldTypes::class)->get('link');
    $field = Field::make('cta', 'link', ['collections' => ['news']]);

    expect(array_map(fn (Field $setting): string => $setting->handle, $type->settings()))->toBe(['collections', 'placeholder'])
        ->and($type->toAdmin($field, Schema::make([$field])))->toMatchArray(['collections' => ['news'], 'type' => 'link']);
});

it('links an untranslated entry at the page\'s language\'s address', function () {
    $story = linkTarget($this->news, 'Story');
    $schema = Schema::make([Field::make('cta', 'link')]);
    $data = $schema->normalize(['cta' => ['type' => 'entry', 'entry_id' => $story->entry_id]]);

    expect($schema->hydrate($data, new HydrationContext('id'))['cta'])->toMatchArray(['url' => url('/id/news/story'), 'title' => 'Story']);
    config(['sunrice-cms.site.fallback' => false]);
    expect($schema->hydrate($data, new HydrationContext('id'))['cta']['url'])->toBe(url('/news/story'));
});

it('reads links saved before kinds, rel and terms existed', function () {
    $about = linkTarget($this->pages, 'About');
    $schema = Schema::make([Field::make('old', 'link'), Field::make('entry', 'link')]);
    // As stored by older versions: an address without a type, an entry link without rel.
    $stored = ['old' => ['url' => '/contact', 'label' => 'Contact', 'new_tab' => true], 'entry' => ['type' => 'entry', 'entry_id' => $about->entry_id, 'label' => null, 'new_tab' => false]];

    expect($schema->hydrate($stored, new HydrationContext('en')))->toBe([
        'old' => ['type' => 'url', 'url' => '/contact', 'label' => 'Contact', 'title' => null, 'new_tab' => true, 'rel' => 'noopener noreferrer'],
        'entry' => ['type' => 'entry', 'url' => url('/about'), 'label' => null, 'title' => 'About', 'new_tab' => false, 'rel' => null],
    ])
        ->and($schema->normalize($stored))->toBe([
            'old' => ['type' => 'url', 'url' => '/contact', 'label' => 'Contact', 'new_tab' => true],
            'entry' => ['type' => 'entry', 'entry_id' => $about->entry_id, 'label' => null, 'new_tab' => false],
        ]);
});

it('stores the rel choices, and gives templates a rel ready to print', function () {
    $schema = Schema::make([Field::make('cta', 'link')]);
    $data = $schema->normalize(['cta' => ['type' => 'url', 'url' => 'https://example.com', 'new_tab' => true, 'rel' => ['ugc', 'junk', 'nofollow', 'nofollow']]]);

    expect($data['cta'])->toBe(['type' => 'url', 'url' => 'https://example.com', 'label' => null, 'new_tab' => true, 'rel' => ['nofollow', 'ugc']])
        ->and($schema->hydrate($data, new HydrationContext('en'))['cta']['rel'])->toBe('nofollow ugc noopener noreferrer')
        ->and($schema->hydrate(['cta' => ['type' => 'url', 'url' => '/a', 'new_tab' => false, 'rel' => ['sponsored']]], new HydrationContext('en'))['cta']['rel'])->toBe('sponsored')
        ->and($schema->hydrate(['cta' => ['type' => 'url', 'url' => '/a', 'new_tab' => false]], new HydrationContext('en'))['cta']['rel'])->toBeNull()
        ->and(linkErrors($schema, ['cta' => ['type' => 'url', 'url' => '/a', 'rel' => ['noopener']]]))->toBe(['data.cta.rel.0'])
        ->and(linkErrors($schema, ['cta' => ['type' => 'url', 'url' => '/a', 'rel' => ['nofollow', 'ugc']]]))->toBe([]);
});

it('links to a term\'s page and a listing page in the page\'s language', function () {
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $coffee = app(SaveTerm::class)->handle($topics, ['title' => 'Coffee']);
    $this->news->update(['listing_route' => '/news']);
    $schema = Schema::make([Field::make('topic', 'link'), Field::make('all', 'link')]);
    $data = $schema->normalize(['topic' => ['type' => 'term', 'term_id' => (string) $coffee->id, 'url' => '/ignored'], 'all' => ['type' => 'listing', 'collection_id' => $this->news->id, 'label' => 'All news']]);

    expect($data)->toBe([
        'topic' => ['type' => 'term', 'term_id' => $coffee->id, 'label' => null, 'new_tab' => false],
        'all' => ['type' => 'listing', 'collection_id' => $this->news->id, 'label' => 'All news', 'new_tab' => false],
    ])
        ->and($schema->hydrate($data, new HydrationContext('en')))->toBe([
            'topic' => ['type' => 'term', 'url' => url('/topics/coffee'), 'label' => null, 'title' => 'Coffee', 'new_tab' => false, 'rel' => null],
            'all' => ['type' => 'listing', 'url' => url('/news'), 'label' => 'All news', 'title' => 'News', 'new_tab' => false, 'rel' => null],
        ])
        ->and($schema->hydrate($data, new HydrationContext('id'))['all']['url'])->toBe(url('/id/news'))
        ->and($schema->references($data))->toEqual([new Reference('term', $coffee->id, 'topic')]);

    app(TrashTerm::class)->handle($coffee);
    $this->news->delete();
    expect($schema->hydrate($data, new HydrationContext('en')))->toMatchArray([
        'topic' => ['type' => 'term', 'url' => null, 'label' => null, 'title' => null, 'new_tab' => false, 'rel' => null],
        'all' => ['type' => 'listing', 'url' => null, 'label' => 'All news', 'title' => null, 'new_tab' => false, 'rel' => null],
    ]);
});

it('checks a term and a listing page exist', function () {
    $topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}']);
    $coffee = app(SaveTerm::class)->handle($topics, ['title' => 'Coffee']);
    $schema = Schema::make([Field::make('cta', 'link')]);

    expect(linkErrors($schema, ['cta' => ['type' => 'term', 'term_id' => $coffee->id]]))->toBe([])
        ->and(linkErrors($schema, ['cta' => ['type' => 'term', 'term_id' => 999999]]))->toBe(['data.cta.term_id'])
        // Pages has no listing page.
        ->and(linkErrors($schema, ['cta' => ['type' => 'listing', 'collection_id' => $this->pages->id]]))->toBe(['data.cta.collection_id'])
        ->and(linkErrors(Schema::make([new Field('cta', 'link', required: true)]), ['cta' => ['type' => 'listing']]))->toContain('data.cta.collection_id');
});

it('reads every listing page a list of links points at in one query', function () {
    $this->news->update(['listing_route' => '/news']);
    $this->pages->update(['listing_route' => '/pages']);
    $schema = Schema::make([Field::make('a', 'link'), Field::make('b', 'link')]);
    $data = ['a' => ['type' => 'listing', 'collection_id' => $this->news->id], 'b' => ['type' => 'listing', 'collection_id' => $this->pages->id]];
    $context = new HydrationContext('en');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $hydrated = $schema->hydrate($data, $context);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(1)->and(array_column($hydrated, 'url'))->toBe([url('/news'), url('/pages')]);
});
