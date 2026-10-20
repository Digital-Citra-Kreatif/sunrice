<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Content;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;

use function Pest\Laravel\actingAs;

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

function queryPost(string $title, array $data = [], bool $publish = true, ?Collection $collection = null): EntryTranslation
{
    $translation = app(CreateEntry::class)->handle($collection ?? test()->blog, ['title' => $title, 'data' => $data]);

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

    expect(countQueries($values))->toBe($few)->toBeLessThanOrEqual(12)
        ->and($values())->toHaveCount(9)
        ->and($values()[0]['cover']?->id)->toBe($cover->id)
        ->and($values()[0]['related']->pluck('title')->all())->toBe(['First', 'Second'])
        ->and($values()[0]['topics']->first()?->title())->toBe('News');

    // Pages, a single entry and the component share a context the same way.
    $paginated = Content::entries('blog')->where('cover', $cover->id)->paginate(4);
    expect(countQueries(fn () => $paginated->getCollection()->each(fn (EntryTranslation $post) => $post->values())))->toBe(0)
        ->and(countQueries(fn () => Content::entries('blog')->where('cover', $cover->id)->first()?->values()))->toBe($few);
    $view = Blade::render('<x-sunrice-cms::entries collection="blog">@foreach ($component->entries as $post){{ $post->values()["cover"]?->filename }};@endforeach</x-sunrice-cms::entries>');
    expect(substr_count($view, 'cover.jpg;'))->toBe(9);
});
