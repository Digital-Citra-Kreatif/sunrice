<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Content;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Query\EntryQuery;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id']]);
    actingAs(adminUser());
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'intro', 'type' => 'text'],
        ['handle' => 'price', 'type' => 'number'],
        ['handle' => 'day', 'type' => 'date'],
        ['handle' => 'featured', 'type' => 'toggle'],
        ['handle' => 'cover', 'type' => 'media'],
        ['handle' => 'related', 'type' => 'entries', 'config' => ['collections' => ['blog'], 'multiple' => true]],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
    ]]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'hierarchical' => true, 'route' => '/topics/{slug}']);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'dated' => true]);
    $this->blog->taxonomies()->attach($this->topics);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'post', 'route' => '/{slug}']);
});

/** @param  list<int>|null  $topics  its own terms (the editor's Taxonomies card) */
function queryPost(string $title, array $data = [], bool $publish = true, ?Collection $collection = null, ?array $topics = null): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection ?? test()->blog, ['title' => $title, 'data' => $data, 'terms' => $topics === null ? null : ['topics' => $topics]]);

    return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
}

function queryTerm(string $title, ?Term $parent = null): Term
{
    return app(SaveTerm::class)->handle(test()->topics, ['title' => $title, 'parent_id' => $parent?->id]);
}

/** How many database queries `$run` takes. */
function countQueries(Closure $run): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $run();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('hydrates a result with one shared context: one query per kind, however many entries', function () {
    $news = queryTerm('News');
    $cover = Media::query()->create(['disk' => 'public', 'path' => 'a/cover.jpg', 'filename' => 'cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 10]);
    $related = [queryPost('First')->entry_id, queryPost('Second')->entry_id];
    $data = ['cover' => $cover->id, 'related' => $related, 'topics' => [$news->id]];
    foreach (range(1, 3) as $i) {
        queryPost("Post {$i}", $data);
    }
    $values = fn () => Content::entries('blog')->where('cover', $cover->id)->get()->map(fn (EntryTranslation $post) => $post->values())->all();
    $few = countQueries($values);
    foreach (range(4, 9) as $i) {
        queryPost("Post {$i}", $data);
    }

    expect(countQueries($values))->toBe($few)->toBeLessThanOrEqual(14)
        ->and($values())->toHaveCount(9)
        ->and($values()[0]['cover']?->id)->toBe($cover->id)
        ->and($values()[0]['related']->pluck('title')->all())->toBe(['First', 'Second'])
        ->and($values()[0]['topics']->first()?->title())->toBe('News');

    // Pages, a single entry and the component share a context the same way.
    // The first values() loads what the whole page points at: the same queries for 2 entries as for 8.
    $page = fn (int $perPage) => fn () => Content::entries('blog')->where('cover', $cover->id)->paginate($perPage)->getCollection()->each(fn (EntryTranslation $post) => $post->values());
    expect(countQueries($page(8)))->toBe(countQueries($page(2)))
        ->and(countQueries(fn () => Content::entries('blog')->where('cover', $cover->id)->first()?->values()))->toBeLessThanOrEqual($few);
    $view = Blade::render('<x-sunrice-cms::entries collection="blog">@foreach ($component->entries as $post){{ $post->values()["cover"]?->filename }};@endforeach</x-sunrice-cms::entries>');
    expect(substr_count($view, 'cover.jpg;'))->toBe(9);
});

it('takes the main language\'s entries not live in a language, with the fallback on', function () {
    $news = queryTerm('News');
    $hello = queryPost('Hello', ['price' => 10], topics: [$news->id]);
    queryPost('Coffee', ['price' => 20], topics: [$news->id]);
    $id = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Halo', 'slug' => 'halo']);
    app(PublishEntry::class)->handle($id->refresh());
    // A translation that isn't live doesn't count: the main language's stands in.
    $coffee = Content::entries('blog')->whereSlug('coffee')->first();
    $kopi = app(CreateTranslation::class)->handle($coffee->entry, 'id');
    app(SaveDraft::class)->handle($kopi, ['title' => 'Kopi', 'slug' => 'kopi']);

    $query = fn () => Content::entries('blog')->locale('id')->orderBy('price');
    expect($query()->get()->pluck('title')->all())->toBe(['Halo', 'Coffee'])
        ->and($query()->count())->toBe(2)
        ->and($query()->whereTerm('topics', 'news')->paginate(10)->total())->toBe(2)
        ->and($query()->where('price', '>', 15)->get()->map->url()->all())->toBe([url('/id/blog/coffee')])
        ->and($query()->search('coffee')->get()->pluck('locale')->all())->toBe(['en'])
        // A preview counts the translation's draft.
        ->and($query()->preview()->get()->pluck('draft_title')->all())->toBe(['Halo', 'Kopi'])
        // Asked not to, or with the fallback off: only the language's own.
        ->and($query()->fallback(false)->get()->pluck('title')->all())->toBe(['Halo']);

    config(['sunrice-cms.site.fallback' => false]);
    expect($query()->get()->pluck('title')->all())->toBe(['Halo']);
});

it('links entries not live in a language to their page at its address', function () {
    $coffee = queryPost('Coffee');
    $kopi = app(CreateTranslation::class)->handle($coffee->entry, 'id'); // a draft: the main language's stands in
    $tea = queryPost('Tea', ['related' => [$coffee->entry_id]]);

    $related = $tea->values(new HydrationContext('id'))['related'];
    expect($related->map->title->all())->toBe(['Coffee'])
        ->and($related->first()?->url())->toBe(url('/id/blog/coffee'))
        ->and($tea->values(new HydrationContext('en'))['related']->first()?->url())->toBe(url('/blog/coffee'));

    app(PublishEntry::class)->handle($kopi);
    expect($tea->values(new HydrationContext('id'))['related']->first()?->locale)->toBe('id');

    // Terms too: an untranslated one's page at the language's address.
    $news = queryTerm('News');
    expect($news->url('id'))->toBe(url('/id/topics/news'))->and($news->url('en'))->toBe(url('/topics/news'));
    config(['sunrice-cms.site.fallback' => false]);
    expect($news->url('id'))->toBeNull();
});

it('finds entries in another language, and drafts in a preview', function () {
    $hello = queryPost('Hello', ['intro' => 'Live intro']);
    $id = app(CreateTranslation::class)->handle($hello->entry, 'id');
    app(SaveDraft::class)->handle($id, ['title' => 'Halo', 'data' => ['intro' => 'Intro']]);
    app(PublishEntry::class)->handle($id);
    app(SaveDraft::class)->handle($hello, ['title' => 'Hello again', 'data' => ['intro' => 'Draft intro']]);
    queryPost('Not yet', publish: false);

    expect(Content::entries('blog')->locale('id')->get()->pluck('title')->all())->toBe(['Halo'])
        ->and(Content::entries('blog')->get()->pluck('title')->all())->toBe(['Hello'])
        ->and(Content::entries('blog')->get()->first()?->values()['intro'])->toBe('Live intro')
        ->and(Content::entries('blog')->preview()->alphabetical()->get()->pluck('draft_title')->all())->toBe(['Hello again', 'Not yet'])
        ->and(Content::entries('blog')->preview()->whereSlug('hello')->where('title', 'Hello again')->first()?->values()['intro'])->toBe('Draft intro')
        ->and(Content::entries('blog')->preview()->where('title', 'Not yet')->count())->toBe(1)
        ->and(Content::entries('blog')->preview()->latest()->get()->pluck('draft_title')->all())->toBe(['Not yet', 'Hello again']);
});

it('searches titles and field text, title matches first, in its collections', function () {
    queryPost('Coffee beans', ['intro' => 'Roasted']);
    queryPost('Morning', ['intro' => 'A coffee ritual']);
    queryPost('Tea', ['intro' => 'Leaves']);
    queryPost('Coffee elsewhere', [], collection: $this->pages);

    expect(Content::entries('blog')->search('coffee')->alphabetical()->get()->pluck('title')->all())->toBe(['Coffee beans', 'Morning'])
        ->and(Content::entries('blog')->alphabetical()->search('coffee')->get()->pluck('title')->all())->toBe(['Coffee beans', 'Morning'])
        ->and(Content::entries()->search('coffee')->count())->toBe(3)
        ->and(Content::entries('blog')->search('  ')->count())->toBe(0)
        ->and(Content::entries('blog')->search('100%')->count())->toBe(0);
    $view = Blade::render('<x-sunrice-cms::entries collection="blog" search="coffee" order="alphabetical">@foreach ($component->entries as $post){{ $post->title }};@endforeach</x-sunrice-cms::entries>');
    expect($view)->toBe('Coffee beans;Morning;');
});

it('filters by terms: models, ids, a taxonomy handle with slugs, with the terms under them', function () {
    $news = queryTerm('News');
    $local = queryTerm('Local', $news);
    $tips = queryTerm('Tips');
    // Their own terms, and the same in a terms field (whereFieldTerm() reads that).
    queryPost('National', ['topics' => [$news->id]], topics: [$news->id]);
    queryPost('Village', ['topics' => [$local->id]], topics: [$local->id]);
    queryPost('Howto', ['topics' => [$tips->id]], topics: [$tips->id]);
    queryPost('Plain');
    // A terms field's value isn't the entry's terms.
    queryPost('Field only', ['topics' => [$tips->id]]);
    $titles = fn (EntryQuery $query) => $query->alphabetical()->get()->pluck('title')->all();

    expect($titles(Content::entries('blog')->whereTerm($news)))->toBe(['National'])
        ->and($titles(Content::entries('blog')->whereTerm($news, $tips->id)))->toBe(['Howto', 'National'])
        ->and($titles(Content::entries('blog')->whereTerm([$news, $tips])))->toBe(['Howto', 'National'])
        ->and($titles(Content::entries('blog')->whereTerm($news, includeChildren: true)))->toBe(['National', 'Village'])
        ->and($titles(Content::entries('blog')->whereTerm('topics', 'news')))->toBe(['National'])
        ->and($titles(Content::entries('blog')->whereTerm('topics', ['news', 'tips'])))->toBe(['Howto', 'National'])
        ->and($titles(Content::entries('blog')->whereTerm('topics', 'news', includeChildren: true)))->toBe(['National', 'Village'])
        ->and($titles(Content::entries('blog')->whereTerm('topics', 'nothing')))->toBe([])
        ->and($titles(Content::entries('blog')->whereTerm('other', 'news')))->toBe([])
        ->and($titles(Content::entries('blog')->whereFieldTerm('topics', $local)))->toBe(['Village'])
        ->and($titles(Content::entries('blog')->whereFieldTerm('topics', 'news', includeChildren: true)))->toBe(['National', 'Village'])
        ->and($titles(Content::entries('blog')->whereFieldTerm('topics', [$tips->id, 'local'])))->toBe(['Field only', 'Howto', 'Village'])
        ->and($titles(Content::entries('blog')->whereFieldTerm('topics', 'nothing')))->toBe([]);
});

it('compares and sorts numbers and dates as such, with lists and null checks', function () {
    travelTo('2026-10-01');
    queryPost('Cheap', ['price' => 9, 'day' => '2026-02-01', 'intro' => 'a']);
    travelTo('2026-10-02');
    queryPost('Dear', ['price' => 100, 'day' => '2026-01-15', 'intro' => 'b']);
    travelTo('2026-10-03');
    queryPost('Mid', ['price' => 50.5, 'day' => '2026-01-20', 'featured' => true]);
    $titles = fn (EntryQuery $query) => $query->get()->pluck('title')->all();

    expect($titles(Content::entries('blog')->where('price', '<', 100)->alphabetical()))->toBe(['Cheap', 'Mid'])
        ->and($titles(Content::entries('blog')->where('price', '>=', '50.5')->alphabetical()))->toBe(['Dear', 'Mid'])
        ->and($titles(Content::entries('blog')->orderBy('price')))->toBe(['Cheap', 'Mid', 'Dear'])
        ->and($titles(Content::entries('blog')->orderBy('price', 'desc')))->toBe(['Dear', 'Mid', 'Cheap'])
        ->and($titles(Content::entries('blog')->where('day', '>', '2026-01-16')->orderBy('day')))->toBe(['Mid', 'Cheap'])
        ->and($titles(Content::entries('blog')->where('day', '<=', Carbon::parse('2026-01-20'))->orderBy('day', 'desc')))->toBe(['Mid', 'Dear'])
        ->and($titles(Content::entries('blog')->where('day', '2026-02-01')))->toBe(['Cheap'])
        ->and($titles(Content::entries('blog')->where('price', 'in', [9, 100])->alphabetical()))->toBe(['Cheap', 'Dear'])
        ->and($titles(Content::entries('blog')->where('intro', 'not in', ['a'])->alphabetical()))->toBe(['Dear'])
        ->and($titles(Content::entries('blog')->where('title', 'in', ['Mid', 'Nope'])))->toBe(['Mid'])
        ->and($titles(Content::entries('blog')->where('intro', null)))->toBe(['Mid'])
        ->and($titles(Content::entries('blog')->where('intro', '!=', null)->alphabetical()))->toBe(['Cheap', 'Dear'])
        ->and($titles(Content::entries('blog')->where('featured', true)))->toBe(['Mid'])
        ->and($titles(Content::entries('blog')->where('published_at', '>=', '2026-10-02')->latest()))->toBe(['Mid', 'Dear'])
        ->and(Content::entries('blog')->toBase()->where('title', 'Mid')->count())->toBe(1);
    expect(fn () => Content::entries('blog')->where('price', 'in', 9))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Content::entries('blog')->where('price', 'like', 9))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Content::entries('blog')->orderBy('x; drop'))->toThrow(InvalidArgumentException::class);
});

it('reads an empty field (JSON null) as no value on every database', function () {
    queryPost('Priced', ['price' => 5, 'intro' => 'a']);
    queryPost('Blank', ['price' => null, 'intro' => null]);
    $titles = fn (EntryQuery $query) => $query->alphabetical()->get()->pluck('title')->all();

    // MySQL's json_unquote() reads JSON null as the string 'null'.
    expect($titles(Content::entries('blog')->where('intro', '!=', 'a')))->toBe([])
        ->and($titles(Content::entries('blog')->where('intro', 'not in', ['b'])))->toBe(['Priced'])
        ->and($titles(Content::entries('blog')->where('intro', '>', 'm')))->toBe([])
        ->and($titles(Content::entries('blog')->where('price', '<', 10)))->toBe(['Priced'])
        ->and($titles(Content::entries('blog')->where('price', null)))->toBe(['Blank'])
        ->and($titles(Content::entries('blog')->where('intro', null)))->toBe(['Blank']);
});

it('sorts entries without a value last, both ways, on every database', function () {
    queryPost('Twenty', ['price' => 20, 'day' => '2026-01-20', 'intro' => 'b']);
    queryPost('Hundred', ['price' => 100, 'day' => '2026-01-03T10:00:00']);
    queryPost('None', ['price' => null, 'day' => null, 'intro' => null]);
    queryPost('Missing');
    queryPost('Negative', ['price' => -3.5, 'day' => '2025-12-31', 'intro' => 'a']);
    $titles = fn (EntryQuery $query) => $query->get()->pluck('title')->all();

    // SQLite and MySQL put nulls first going up, Postgres going down.
    expect($titles(Content::entries('blog')->orderBy('price')))->toBe(['Negative', 'Twenty', 'Hundred', 'None', 'Missing'])
        ->and($titles(Content::entries('blog')->orderBy('price', 'desc')))->toBe(['Hundred', 'Twenty', 'Negative', 'None', 'Missing'])
        ->and($titles(Content::entries('blog')->orderBy('day')))->toBe(['Negative', 'Hundred', 'Twenty', 'None', 'Missing'])
        ->and($titles(Content::entries('blog')->orderBy('day', 'desc')))->toBe(['Twenty', 'Hundred', 'Negative', 'None', 'Missing'])
        ->and($titles(Content::entries('blog')->orderBy('intro')))->toBe(['Negative', 'Twenty', 'Hundred', 'None', 'Missing'])
        ->and($titles(Content::entries('blog')->orderBy('intro', 'desc')))->toBe(['Twenty', 'Negative', 'Hundred', 'None', 'Missing'])
        // Another language reads the main language's values: the same order.
        ->and($titles(Content::entries('blog')->locale('id')->orderBy('price', 'desc')))->toBe(['Hundred', 'Twenty', 'Negative', 'None', 'Missing'])
        ->and($titles(Content::entries('blog')->locale('id')->orderBy('price')))->toBe(['Negative', 'Twenty', 'Hundred', 'None', 'Missing']);
});

it('reads text that isn\'t a number or a date as no value, without failing, on every database', function () {
    queryPost('Twenty', ['price' => 20, 'day' => '2026-01-20']);
    // As a field that was text before its blueprint made it a number or date keeps it.
    $words = queryPost('Words');
    $words->forceFill(['data' => ['price' => 'abc', 'day' => 'soon'], 'draft_data' => ['price' => 'abc', 'day' => 'soon']])->save();
    $digits = queryPost('Digits');
    $digits->forceFill(['data' => ['price' => ' 100 ', 'day' => ''], 'draft_data' => ['price' => ' 100 ', 'day' => '']])->save();
    $titles = fn (EntryQuery $query) => $query->get()->pluck('title')->all();

    // Postgres failed the whole query ("invalid input syntax"); SQLite and MySQL read "abc" as 0.
    expect($titles(Content::entries('blog')->orderBy('price')))->toBe(['Twenty', 'Digits', 'Words'])
        ->and($titles(Content::entries('blog')->orderBy('day')))->toBe(['Twenty', 'Words', 'Digits'])
        ->and($titles(Content::entries('blog')->where('price', '<', 50)))->toBe(['Twenty'])
        ->and($titles(Content::entries('blog')->where('price', '>', 50)))->toBe(['Digits'])
        ->and($titles(Content::entries('blog')->where('day', '>', '2026-01-01')))->toBe(['Twenty']);
});

it('remembers matched ids in the site cache until the content changes', function () {
    $a = queryPost('A');
    queryPost('B');
    $titles = fn () => Content::entries('blog')->remember()->alphabetical()->get()->pluck('title')->all();
    $count = fn () => Content::entries('blog')->remember()->count();
    expect($titles())->toBe(['A', 'B'])->and($count())->toBe(2);

    // A change the site doesn't hear about (no model events): the cached ids stay.
    EntryTranslation::query()->whereKey($a->id)->update(['status' => 'draft']);
    expect($titles())->toBe(['A', 'B'])->and($count())->toBe(2)
        ->and(Content::entries('blog')->alphabetical()->get()->pluck('title')->all())->toBe(['B']);

    // Anything published or changed through the CMS replaces the version.
    queryPost('C');
    expect($titles())->toBe(['B', 'C'])
        ->and(Content::entries('blog')->remember()->paginate(1)->total())->toBe(2)
        ->and(Content::entries('blog')->remember()->alphabetical()->first()?->title)->toBe('B')
        ->and(Content::entries('blog')->remember()->preview()->count())->toBe(3);
});

it('adds a condition only when a value is given (when, unless)', function () {
    queryPost('Cheap', ['price' => 5]);
    queryPost('Dear', ['price' => 50]);
    $titles = fn (?int $max) => Content::entries('blog')
        ->when($max, fn (EntryQuery $q, int $max) => $q->where('price', '<=', $max))
        ->alphabetical()->get()->pluck('title')->all();

    expect($titles(10))->toBe(['Cheap'])
        ->and($titles(null))->toBe(['Cheap', 'Dear'])
        ->and(Content::entries('blog')->unless(true, fn (EntryQuery $q) => $q->where('price', '>', 10))->count())->toBe(2)
        ->and(Content::entries('blog')->unless(false, fn (EntryQuery $q) => $q->where('price', '>', 10))->count())->toBe(1);
});
