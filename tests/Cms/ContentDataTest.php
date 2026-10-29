<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Sunrice\Admin\Actions\Media\TrashMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Settings\LocaleSettings;
use Sunrice\Cms\Site\Page;
use Sunrice\Cms\Support\Locales;

use function Pest\Laravel\getJson;

/*
 * toData(): plain arrays of entries, terms and media for scripts on a page
 * and small JSON routes, made from what templates get.
 */

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    Fieldset::query()->create(['handle' => 'quote', 'title' => 'Quote', 'fields' => [
        ['handle' => 'text', 'type' => 'text'],
        ['handle' => 'photo', 'type' => 'media'],
    ]]);
    Blueprint::query()->create(['handle' => 'agent', 'title' => 'Agent', 'fields' => [['handle' => 'phone', 'type' => 'text']]]);
    Blueprint::query()->create(['handle' => 'place', 'title' => 'Place', 'fields' => [
        ['handle' => 'intro', 'type' => 'text', 'translatable' => true],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'price', 'type' => 'number', 'translatable' => false],
        ['handle' => 'open', 'type' => 'toggle'],
        ['handle' => 'day', 'type' => 'date'],
        ['handle' => 'status', 'type' => 'select', 'config' => ['options' => [['value' => 'open', 'label' => 'Open'], ['value' => 'sold', 'label' => 'Sold']]]],
        ['handle' => 'features', 'type' => 'select', 'config' => ['multiple' => true, 'options' => [['value' => 'pool', 'label' => 'Pool'], ['value' => 'view', 'label' => 'View']]]],
        ['handle' => 'cover', 'type' => 'media', 'translatable' => false],
        ['handle' => 'gallery', 'type' => 'media', 'config' => ['multiple' => true]],
        ['handle' => 'agent', 'type' => 'entries', 'config' => ['collections' => ['agents']]],
        ['handle' => 'nearby', 'type' => 'entries', 'config' => ['collections' => ['places'], 'multiple' => true]],
        ['handle' => 'area', 'type' => 'terms', 'config' => ['taxonomy' => 'topics']],
        ['handle' => 'snippet', 'type' => 'code', 'config' => ['language' => 'html']],
        ['handle' => 'cta', 'type' => 'link'],
        ['handle' => 'location', 'type' => 'group', 'fields' => [
            ['handle' => 'lat', 'type' => 'number'],
            ['handle' => 'lng', 'type' => 'number'],
            ['handle' => 'photo', 'type' => 'media'],
        ]],
        ['handle' => 'stops', 'type' => 'repeater', 'fields' => [
            ['handle' => 'name', 'type' => 'text'],
            ['handle' => 'photo', 'type' => 'media'],
        ]],
        ['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['quote']]],
    ]]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'hierarchical' => true, 'route' => '/topics/{slug}']);
    $this->places = Collection::query()->create(['handle' => 'places', 'title' => 'Places', 'blueprint' => 'place', 'route' => '/places/{slug}', 'dated' => true, 'hierarchical' => true]);
    $this->agents = Collection::query()->create(['handle' => 'agents', 'title' => 'Agents', 'blueprint' => 'agent', 'route' => '/agents/{slug}']);
    $this->places->taxonomies()->attach($this->topics);
    $this->agents->taxonomies()->attach($this->topics);
});

/** What a page printed with @json in an element with this id or attribute. */
function printedData(string $html, string $pattern): mixed
{
    expect(preg_match($pattern, $html, $match))->toBe(1);

    return json_decode(html_entity_decode($match[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
}

function dataMedia(string $name, array $attributes = []): Media
{
    return Media::query()->create([
        'disk' => 'public', 'path' => "media/{$name}.jpg", 'filename' => "{$name}.jpg", 'mime_type' => 'image/jpeg', 'size' => 1,
        'width' => 1600, 'height' => 1200, 'alt' => "Alt {$name}", 'title' => ucfirst($name), 'sizes' => ['medium' => "media/{$name}-medium.jpg"], ...$attributes,
    ]);
}

function dataTerm(string $title, ?Term $parent = null): Term
{
    return app(SaveTerm::class)->handle(test()->topics, ['title' => $title, 'parent_id' => $parent?->id]);
}

/** @param  list<int>  $terms  its own terms */
function dataPlace(string $title, array $data = [], array $terms = [], ?Collection $collection = null): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection ?? test()->places, ['title' => $title, 'data' => $data, 'terms' => ['topics' => $terms]]));
}

it('gives an entry\'s data as its page shows it, with its terms by taxonomy', function () {
    $bali = dataTerm('Bali');
    $ubud = dataTerm('Ubud', $bali);
    $parent = dataPlace('Island');
    $villa = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->places, ['title' => 'Villa', 'parent_id' => $parent->entry_id, 'terms' => ['topics' => [$bali->id, $ubud->id]]]));
    $villa = Cms::entries('places')->whereSlug('villa')->first();

    expect($villa?->toData())->toBe([
        'id' => $villa->entry_id,
        'locale' => 'en',
        'title' => 'Villa',
        'slug' => 'villa',
        'url' => url('/places/villa'),
        'collection' => 'places',
        'published_at' => $villa->published_at?->format(DATE_ATOM),
        'parent_id' => $parent->entry_id,
        'terms' => ['topics' => [
            ['id' => $bali->id, 'taxonomy' => 'topics', 'locale' => 'en', 'title' => 'Bali', 'slug' => 'bali', 'url' => url('/topics/bali'), 'parent_id' => null],
            ['id' => $ubud->id, 'taxonomy' => 'topics', 'locale' => 'en', 'title' => 'Ubud', 'slug' => 'ubud', 'url' => url('/topics/ubud'), 'parent_id' => $bali->id],
        ]],
    ]);
});

it('gives a term\'s data in the page\'s language, the main one standing in', function () {
    $news = dataTerm('News');
    $tips = dataTerm('Tips');
    app(SaveTerm::class)->handle($this->topics, ['title' => 'Berita', 'slug' => 'berita', 'locale' => 'id'], $news);
    $news = Term::query()->with('translations', 'taxonomy')->findOrFail($news->id);
    $tips = Term::query()->with('translations', 'taxonomy')->findOrFail($tips->id);

    expect($news->toData('id'))->toBe(['id' => $news->id, 'taxonomy' => 'topics', 'locale' => 'id', 'title' => 'Berita', 'slug' => 'berita', 'url' => url('/id/topics/berita'), 'parent_id' => null])
        ->and($news->toData())->toMatchArray(['locale' => 'en', 'title' => 'News', 'url' => url('/topics/news')])
        ->and($tips->toData('id'))->toMatchArray(['locale' => 'en', 'title' => 'Tips', 'slug' => 'tips', 'url' => url('/id/topics/tips')])
        ->and($news->translation('id')?->toData())->toBe($news->toData('id'));
    app()->setLocale('id');
    expect($news->toData()['title'])->toBe('Berita');
});

it('adds only the fields asked for, converted from what templates get', function () {
    $bali = dataTerm('Bali');
    [$cover, $one, $two, $pin, $stop, $quote] = [dataMedia('cover'), dataMedia('one'), dataMedia('two'), dataMedia('pin'), dataMedia('stop'), dataMedia('quote')];
    $agent = dataPlace('Ayu', ['phone' => '+62 000'], [$bali->id], $this->agents);
    $other = dataPlace('Other');
    dataPlace('Villa', [
        'intro' => 'Quiet', 'body' => '<p>A <strong>calm</strong> villa.</p>', 'price' => 150000, 'open' => true, 'day' => '2026-10-19',
        'status' => 'sold', 'features' => ['pool', 'view'], 'cover' => $cover->id, 'gallery' => [$two->id, $one->id],
        'agent' => $agent->entry_id, 'nearby' => [$other->entry_id], 'area' => $bali->id, 'snippet' => '<iframe src="https://example.com"></iframe>',
        'cta' => ['type' => 'url', 'url' => 'https://example.com/book', 'label' => 'Book', 'new_tab' => true],
        'location' => ['lat' => -8.5, 'lng' => 115.26, 'photo' => $pin->id],
        'stops' => [['_id' => 'a', 'name' => 'Market', 'photo' => $stop->id], ['_id' => 'b', 'name' => 'Hidden', '_hidden' => true]],
        'sections' => [['id' => 'q1', 'type' => 'quote', 'key' => 'top', 'hidden' => false, 'values' => ['text' => 'Lovely', 'photo' => $quote->id]]],
    ]);
    $villa = Cms::entries('places')->whereSlug('villa')->first();
    $all = ['intro', 'body', 'price', 'open', 'day', 'status', 'features', 'cover', 'gallery', 'agent', 'nearby', 'area', 'snippet', 'cta', 'location', 'stops', 'sections'];
    $fields = $villa?->toData([...$all, 'nothing', 'seo'])['fields'] ?? [];

    expect(array_keys($fields))->toBe($all) // unknown handles left out
        ->and($fields['intro'])->toBe('Quiet')
        ->and($fields['body'])->toBe($villa?->values()['body'])->toContain('<strong>calm</strong>')
        ->and($fields['price'])->toBe(150000)
        ->and($fields['open'])->toBeTrue()
        ->and($fields['day'])->toBe('2026-10-19T00:00:00+00:00')
        ->and($fields['status'])->toBe('sold')
        ->and($fields['features'])->toBe(['pool', 'view'])
        ->and($fields['cover'])->toBe($cover->toData())
        ->and(array_column($fields['gallery'], 'id'))->toBe([$two->id, $one->id])
        ->and($fields['gallery'][0]['sizes']['medium']['url'])->toBe($two->url('medium'))
        ->and($fields['area'])->toBe($bali->fresh()?->load('translations', 'taxonomy')->toData())
        ->and($fields['snippet'])->toBe('<iframe src="https://example.com"></iframe>')
        ->and($fields['cta'])->toBe(['type' => 'url', 'url' => 'https://example.com/book', 'label' => 'Book', 'title' => null, 'new_tab' => true, 'rel' => 'noopener noreferrer'])
        ->and($fields['location'])->toBe(['lat' => -8.5, 'lng' => 115.26, 'photo' => $pin->toData()])
        ->and($fields['stops'])->toBe([['name' => 'Market', 'photo' => $stop->toData()]])
        ->and($fields['sections'])->toBe([['type' => 'quote', 'key' => 'top', 'values' => ['text' => 'Lovely', 'photo' => $quote->toData()]]]);

    // Linked entries come as their data with their terms, never with fields of their own.
    expect($fields['agent'])->toMatchArray(['id' => $agent->entry_id, 'title' => 'Ayu', 'collection' => 'agents', 'url' => url('/agents/ayu')])
        ->and($fields['agent']['terms']['topics'][0]['title'])->toBe('Bali')
        ->and($fields['agent'])->not->toHaveKey('fields')
        ->and($fields['nearby'][0])->toMatchArray(['id' => $other->entry_id, 'title' => 'Other'])->not->toHaveKey('fields');

    // No fields asked for: no fields.
    expect($villa?->toData())->not->toHaveKey('fields')
        ->and($villa?->toData(['price'])['fields'])->toBe(['price' => 150000]);
});

it('never gives stored data the blueprint doesn\'t define', function () {
    $villa = dataPlace('Villa', ['intro' => 'Quiet']);
    EntryTranslation::query()->whereKey($villa->id)->update(['data' => json_encode(['intro' => 'Quiet', 'secret' => 'kept from an old field'])]);

    expect(Cms::entries('places')->first()?->toData(['intro', 'secret'])['fields'])->toBe(['intro' => 'Quiet']);
});

it('leaves out media in the trash, as templates do', function () {
    [$cover, $one, $two] = [dataMedia('cover'), dataMedia('one'), dataMedia('two')];
    dataPlace('Villa', ['cover' => $cover->id, 'gallery' => [$one->id, $two->id]]);
    app(TrashMedia::class)->handle($cover);
    app(TrashMedia::class)->handle($one);

    $fields = Cms::entries('places')->first()?->toData(['cover', 'gallery'])['fields'];
    expect($fields)->toHaveKey('cover', null)
        ->and(array_column($fields['gallery'] ?? [], 'id'))->toBe([$two->id]);
});

it('gives the main language\'s values where an entry isn\'t translated, and shared fields from the main one', function () {
    $news = dataTerm('News');
    app(SaveTerm::class)->handle($this->topics, ['title' => 'Berita', 'slug' => 'berita', 'locale' => 'id'], $news);
    $cover = dataMedia('cover');
    $cover->forceFill(['translations' => ['id' => ['alt' => 'Sampul']]])->save();
    $villa = dataPlace('Villa', ['intro' => 'Quiet', 'price' => 100, 'cover' => $cover->id], [$news->id]);
    dataPlace('Cabin', ['intro' => 'Small', 'price' => 50], [$news->id]);
    $id = app(CreateTranslation::class)->handle($villa->entry, 'id');
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle($id, ['title' => 'Vila', 'slug' => 'vila', 'data' => ['intro' => 'Tenang']]));

    $data = collect(Cms::toData(Cms::entries('places')->locale('id')->alphabetical()->get(), ['intro', 'price', 'cover']))->keyBy('title');

    expect($data['Vila'])->toMatchArray(['locale' => 'id', 'slug' => 'vila', 'url' => url('/id/places/vila')])
        ->and($data['Vila']['fields'] ?? [])->toMatchArray(['intro' => 'Tenang', 'price' => 100]) // the price is shared: the main one's
        ->and($data['Vila']['fields']['cover']['alt'] ?? null)->toBe('Sampul')
        ->and($data['Vila']['terms']['topics'][0]['title'])->toBe('Berita')
        // Not translated: the main language's content, at this language's address.
        ->and($data['Cabin'])->toMatchArray(['locale' => 'en', 'slug' => 'cabin', 'url' => url('/id/places/cabin')])
        ->and($data['Cabin']['fields'] ?? [])->toBe(['intro' => 'Small', 'price' => 50, 'cover' => null])
        ->and($data['Cabin']['terms']['topics'][0]['title'])->toBe('Berita');
});

it('gives the live version, and the draft in a preview, as templates do', function () {
    $villa = dataPlace('Villa', ['intro' => 'Live']);
    app(SaveDraft::class)->handle($villa, ['title' => 'Villa (new)', 'slug' => 'villa-new', 'data' => ['intro' => 'Draft']]);
    $never = app(CreateEntry::class)->handle($this->places, ['title' => 'Not yet', 'data' => ['intro' => 'Draft only']]);

    expect(Cms::toData(Cms::entries('places')->get(), ['intro']))->toHaveCount(1)
        ->and(Cms::entries('places')->first()?->toData(['intro']))->toMatchArray(['title' => 'Villa', 'slug' => 'villa', 'fields' => ['intro' => 'Live']]);

    $preview = collect(Cms::toData(Cms::entries('places')->preview()->alphabetical()->get(), ['intro']))->keyBy('id');
    expect($preview[$villa->entry_id])->toMatchArray(['title' => 'Villa (new)', 'slug' => 'villa-new', 'fields' => ['intro' => 'Draft']])
        ->and($preview[$never->entry_id])->toMatchArray(['title' => 'Not yet', 'url' => null, 'published_at' => null, 'fields' => ['intro' => 'Draft only']]);
});

it('gives the entry page\'s $entry as the page shows it, the draft in its preview', function () {
    $villa = dataPlace('Villa', ['intro' => 'Live']);
    app(SaveDraft::class)->handle($villa, ['title' => 'Villa (new)', 'data' => ['intro' => 'Draft']]);
    $views = sys_get_temp_dir().'/sunrice-data-'.uniqid();
    mkdir("{$views}/cms/collections/places", recursive: true);
    file_put_contents("{$views}/cms/collections/places/show.blade.php", '<script type="application/json" id="data" nonce="{{ cms_nonce() }}">@json($entry->toData([\'intro\']))</script>');
    View::addLocation($views);

    $live = printedData($this->get('/places/villa')->assertOk()->getContent() ?: '', '#<script type="application/json" id="data" nonce="[^"]*">(.*?)</script>#s');
    expect($live)->toMatchArray(['title' => 'Villa', 'fields' => ['intro' => 'Live']]);
    $preview = $this->get(URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(5), ['translation' => $villa->id]))->assertOk()->getContent() ?: '';
    expect(printedData($preview, '#<script type="application/json" id="data" nonce="[^"]*">(.*?)</script>#s'))->toMatchArray(['title' => 'Villa (new)', 'fields' => ['intro' => 'Draft']]);
});

it('gives a page of entries as data and meta, and any list as a list', function () {
    foreach (['A', 'B', 'C'] as $title) {
        dataPlace($title, ['price' => 1]);
    }
    $page = Cms::toData(Cms::entries('places')->alphabetical()->paginate(2), ['price']);

    expect(array_keys($page))->toBe(['data', 'meta'])
        ->and(array_column($page['data'], 'title'))->toBe(['A', 'B'])
        ->and($page['data'][0]['fields'] ?? null)->toBe(['price' => 1])
        ->and($page['meta'])->toBe(['current_page' => 1, 'last_page' => 2, 'per_page' => 2, 'total' => 3, 'next_url' => url('/').'?page=2', 'prev_url' => null])
        ->and(array_column(Cms::toData(Cms::entries('places')->alphabetical()->limit(2)->get()), 'title'))->toBe(['A', 'B'])
        ->and(Cms::toData([dataMedia('x'), dataTerm('Bali'), 'not a record']))->toHaveCount(2);

    // A small JSON route of the app's, for "load more".
    Route::get('/places.json', fn () => Cms::toData(Cms::entries('places')->alphabetical()->paginate(2), ['price']));
    getJson('/places.json?page=2')->assertOk()
        ->assertJsonPath('data.0.title', 'C')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.next_url', null)
        ->assertJsonPath('meta.prev_url', url('/places.json'));
});

it('converts a list of entries with their media, links and terms in a fixed number of queries', function () {
    $make = function (int $count): void {
        static $n = 0;
        foreach (range(1, $count) as $i) {
            $n++;
            $term = dataTerm("Topic {$n}");
            $agent = dataPlace("Agent {$n}", ['phone' => '1'], [$term->id], $this->agents);
            dataPlace("Place {$n}", [
                'cover' => dataMedia("c{$n}")->id, 'gallery' => [dataMedia("g{$n}")->id], 'agent' => $agent->entry_id, 'area' => $term->id,
                'location' => ['photo' => dataMedia("p{$n}")->id], 'stops' => [['_id' => 's', 'name' => 'x', 'photo' => dataMedia("s{$n}")->id]],
            ], [$term->id]);
        }
    };
    $queries = function (): int {
        Cms::toData(Cms::entries('places')->get(), ['cover', 'gallery', 'agent', 'area', 'location', 'stops']); // warm the site cache
        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = Cms::toData(Cms::entries('places')->get(), ['cover', 'gallery', 'agent', 'area', 'location', 'stops']);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        expect($data[0]['fields']['agent']['terms']['topics'] ?? [])->toHaveCount(1);

        return $count;
    };

    $make(2);
    $few = $queries();
    $make(10);

    expect($queries())->toBe($few)->toBeLessThanOrEqual(15);
});

it('converts a list in another language, on a collection\'s term page, in a fixed number of queries', function () {
    // Term pages in the collection (the page shown's), the main language standing in, an own pattern in Indonesian.
    $this->places->taxonomies()->updateExistingPivot($this->topics->id, ['term_pages' => true]);
    $this->places->setOwnRoute('id', '/tempat/{slug}')->save();
    $shared = dataTerm('Shared');
    app(SaveTerm::class)->handle($this->topics, ['title' => 'Bersama', 'slug' => 'bersama', 'locale' => 'id'], $shared);
    $make = function (int $count) use ($shared): void {
        static $n = 0;
        foreach (range(1, $count) as $i) {
            $n++;
            $term = dataTerm("Topic {$n}", $shared);
            $agent = dataPlace("Agent {$n}", ['phone' => '1'], [$term->id, $shared->id], $this->agents);
            $place = dataPlace("Place {$n}", ['cover' => dataMedia("c{$n}")->id, 'agent' => $agent->entry_id, 'area' => $shared->id], [$term->id, $shared->id]);
            if ($n % 2 === 0) {
                app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($place->entry, 'id'), ['title' => "Tempat {$n}", 'slug' => "tempat-{$n}"]));
            }
        }
    };
    app()->setLocale('id');
    $queries = function (): array {
        (new Page(Page::LISTING, 'id', 'cms.listing', collection: $this->places))->share();
        Cms::toData(Cms::entries('places')->get(), ['cover', 'agent', 'area']); // warm the site cache
        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = Cms::toData(Cms::entries('places')->get(), ['cover', 'agent', 'area']);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [$count, $data];
    };

    $make(2);
    [$few] = $queries();
    $make(10);
    [$many, $data] = $queries();

    expect($many)->toBe($few)->toBeLessThanOrEqual(15)
        ->and($data)->toHaveCount(12);
    $bersama = ['id' => $shared->id, 'taxonomy' => 'topics', 'locale' => 'id', 'title' => 'Bersama', 'slug' => 'bersama', 'url' => url('/id/places/topics/bersama'), 'parent_id' => null];
    foreach ($data as $place) {
        // Its URL in Indonesian (its own, or the main one's at the Indonesian address), every term at its page in the collection.
        expect($place['url'])->toStartWith(url('/id/tempat/'))
            ->and($place['terms']['topics'])->toContain($bersama)
            ->and($place['fields']['area'])->toBe($bersama)
            ->and($place['fields']['agent']['url'])->toStartWith(url('/id/agents/'))
            ->and($place['fields']['agent']['terms']['topics'])->toContain($bersama);
    }
});

it('makes a term\'s data once per list, its URL following the page shown', function () {
    $this->places->taxonomies()->updateExistingPivot($this->topics->id, ['term_pages' => true]);
    $bali = dataTerm('Bali');
    dataPlace('Villa', [], [$bali->id]);
    dataPlace('Hut', [], [$bali->id]);
    $list = Cms::entries('places')->get();

    expect(collect(Cms::toData($list))->pluck('terms.topics.0.url')->all())->toBe([url('/topics/bali'), url('/topics/bali')]);
    // The same list (and context) on the collection's page: its term pages there.
    (new Page(Page::LISTING, 'en', 'cms.listing', collection: $this->places))->share();
    expect(collect(Cms::toData($list))->pluck('terms.topics.0.url')->all())->toBe([url('/places/topics/bali'), url('/places/topics/bali')]);
});

it('keeps what URLs are built from for one request only, and reads it again when it changes', function () {
    $villa = dataPlace('Villa');
    $villa = Cms::entries('places')->whereSlug('villa')->first();
    expect($villa?->url())->toBe(url('/places/villa'));

    // In memory: a language's own pattern, the languages and a publish date, each read again once changed.
    $this->places->setOwnRoute('id', '/tempat/{slug}');
    expect($this->places->routeIn('id'))->toBe('/tempat/{slug}');
    $this->places->setOwnRoute('id', '/vila/{slug}');
    expect($this->places->routeIn('id'))->toBe('/vila/{slug}');
    $languages = app(LocaleSettings::class);
    $languages->languages = "en: English\nid: Indonesia";
    expect(Locales::prefix('id'))->toBe('/id');
    $languages->languages = "en: English\nid: Indonesia | indo";
    expect(Locales::prefix('id'))->toBe('/indo')
        ->and($villa?->isLive())->toBeTrue();
    $villa->published_at = now()->addDay();
    expect($villa->isLive())->toBeFalse();

    // Saved: the next request (a fresh Site, as under Octane) builds its URLs from the new pattern.
    $this->places->update(['route' => '/stays/{slug}']);
    app()->forgetScopedInstances();
    expect(Cms::entries('places')->whereSlug('villa')->first()?->url())->toBe(url('/stays/villa'));
});

it('prints data into an attribute with @json, safely', function () {
    dataPlace('Villa \'<b>\'', ['location' => ['lat' => -8.5, 'lng' => 115.26]]);
    // @json takes one expression without commas (it splits on them): name the data first.
    $html = Blade::render('@php($pins = Cms::toData(Cms::entries(\'places\')->get(), [\'location\']))<div data-pins=\'@json($pins)\'></div>');
    $pins = printedData($html, "#data-pins='([^']*)'#");

    expect($html)->not->toContain('<b>')->not->toContain("Villa '")
        ->and($pins[0]['title'])->toBe('Villa \'<b>\'')
        ->and($pins[0]['fields']['location'])->toBe(['lat' => -8.5, 'lng' => 115.26, 'photo' => null]);
});
