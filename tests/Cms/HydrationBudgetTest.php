<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\get;

/*
 * Templates that call values() on every card, and on the entries those
 * point at, cost the same number of queries however many cards there are.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en']]);
    Blueprint::query()->create(['handle' => 'agent', 'title' => 'Agent', 'fields' => [['handle' => 'photo', 'type' => 'media']]]);
    Blueprint::query()->create(['handle' => 'property', 'title' => 'Property', 'fields' => [
        ['handle' => 'photo', 'type' => 'media'],
        ['handle' => 'agent', 'type' => 'entries', 'config' => ['collections' => ['agents']]],
        ['handle' => 'tags', 'type' => 'terms', 'config' => ['taxonomy' => 'tags', 'multiple' => true]],
        ['handle' => 'area', 'type' => 'terms', 'config' => ['taxonomy' => 'tags']],
        ['handle' => 'related', 'type' => 'entries', 'config' => ['collections' => ['properties'], 'multiple' => true]],
        ['handle' => 'others', 'type' => 'entries', 'config' => ['collections' => ['properties'], 'multiple' => true]],
    ]]);
    Blueprint::query()->create(['handle' => 'page', 'title' => 'Page', 'fields' => [
        ['handle' => 'featured', 'type' => 'entries', 'config' => ['collections' => ['properties'], 'multiple' => true]],
    ]]);
    $this->agents = Collection::query()->create(['handle' => 'agents', 'title' => 'Agents', 'blueprint' => 'agent', 'route' => '/agents/{slug}']);
    $this->properties = Collection::query()->create(['handle' => 'properties', 'title' => 'Properties', 'blueprint' => 'property', 'route' => '/properties/{slug}', 'listing_route' => '/properties', 'per_page' => 50]);
    $this->pages = Collection::query()->create(['handle' => 'pages', 'title' => 'Pages', 'blueprint' => 'page', 'route' => '/{slug}']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'route' => '/tags/{slug}', 'hierarchical' => true]);
    $this->tags->collections()->attach($this->properties);
    $this->bali = app(SaveTerm::class)->handle($this->tags, ['title' => 'Bali']);

    $card = '[{{ $p->title }}|{{ $p->url() }}|@php($v = $p->values()){{ $v[\'photo\']?->url() }}|{{ $v[\'agent\']?->title }}|{{ $v[\'agent\']?->values()[\'photo\']?->url() }}'
        .'|@foreach ($v[\'tags\'] as $t){{ $t->title() }} {{ $t->url() }} {{ $t->parent?->title() }}@endforeach|{{ $v[\'area\']?->title() }}]';
    $views = sys_get_temp_dir().'/sunrice-hydration-'.uniqid();
    foreach (['collections/pages', 'collections/properties', 'taxonomies/tags'] as $folder) {
        mkdir("{$views}/cms/{$folder}", recursive: true);
    }
    file_put_contents("{$views}/cms/collections/properties/index.blade.php", '@foreach ($entries as $p)'.$card.'@endforeach');
    file_put_contents("{$views}/cms/taxonomies/tags/show.blade.php", '@foreach ($entries as $p)'.$card.'@endforeach');
    file_put_contents("{$views}/cms/search.blade.php", '@foreach ($results as $p)'.$card.'@endforeach');
    file_put_contents("{$views}/cms/collections/pages/show.blade.php", '@foreach ($values[\'featured\'] as $p)'.$card.'@endforeach');
    file_put_contents("{$views}/cms/collections/properties/show.blade.php", str_replace('$p->values()', '$entry->values()', str_replace('$p->', '$entry->', $card))
        .'@foreach ([...$values[\'related\'], ...$values[\'others\']] as $p)'.$card.'@endforeach');
    View::addLocation($views);
});

/** Makes `$count` properties more (each with an agent, a photo, tags and a related property), and returns them. */
function properties(int $count): array
{
    $made = [];
    $start = EntryTranslation::query()->count();
    foreach (range(1, $count) as $i) {
        $n = $start + $i;
        $media = fn () => Media::query()->create(['disk' => 'public', 'path' => "media/{$n}-".uniqid().'.jpg', 'filename' => "{$n}.jpg", 'mime_type' => 'image/jpeg', 'size' => 1])->id;
        $agent = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->agents, ['title' => "Agent {$n}", 'data' => ['photo' => $media()]]));
        $tag = app(SaveTerm::class)->handle(test()->tags, ['title' => "Tag {$n}", 'parent_id' => test()->bali->id]);
        $made[] = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->properties, ['title' => "Villa {$n}", 'data' => [
            'photo' => $media(), 'agent' => $agent->entry_id, 'tags' => [test()->bali->id, $tag->id], 'area' => $tag->id,
            'related' => $made === [] ? [] : [end($made)->entry_id], 'others' => array_map(fn (EntryTranslation $t): int => $t->entry_id, $made),
        ]]));
    }

    return $made;
}

function queriesFor(string $path, string $see): int
{
    get($path)->assertOk()->assertSee($see); // warm the site cache
    DB::flushQueryLog();
    DB::enableQueryLog();
    get($path)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('hydrates a listing\'s, a term page\'s and the search\'s cards together', function () {
    commitForFullText(); // the search's FULLTEXT index on MySQL sees committed rows only
    properties(2);
    $few = [queriesFor('/properties', 'Agent 1'), queriesFor('/tags/bali', 'Agent 1'), queriesFor('/search?q=villa', 'Agent 1')];
    properties(6);
    $many = [queriesFor('/properties', 'Agent 8'), queriesFor('/tags/bali', 'Agent 8'), queriesFor('/search?q=villa', 'Agent 8')];

    // Was 46/48/43 for two cards and 181/183/178 for eight: about five queries a card.
    expect($many)->toBe($few)->and(max($many))->toBeLessThanOrEqual(19);
});

it('hydrates an entries field\'s chosen entries, and what they point at, together', function () {
    $few = properties(2);
    $home = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->pages, ['title' => 'Home', 'data' => ['featured' => array_map(fn ($t) => $t->entry_id, $few)]]));
    $before = queriesFor('/home', 'Agent 2');
    $many = [...$few, ...properties(6)];
    app(PublishEntry::class)->handle(tap($home->fresh(), fn ($t) => $t->update(['draft_data' => ['featured' => array_map(fn ($t) => $t->entry_id, $many)]])));

    expect(queriesFor('/home', 'Agent 8'))->toBe($before)->toBeLessThanOrEqual(18); // was 53 for two, 206 for eight
});

it('loads an entry page\'s linked records one query per kind, however many fields and entries', function () {
    $few = properties(2);
    $before = queriesFor('/properties/villa-2', 'Agent 1');
    $many = properties(6);

    // Each level of links (the entry's, then what its linked entries point at) costs one query per kind.
    expect(queriesFor('/properties/villa-8', 'Agent 5'))->toBe($before)->toBeLessThanOrEqual(19);
    expect(end($many)->values()['others'])->toHaveCount(5);
});
