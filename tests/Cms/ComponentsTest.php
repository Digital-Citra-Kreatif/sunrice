<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Site\Page;

use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en'], 'app.name' => 'Acme']);
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [
        ['handle' => 'city', 'type' => 'text'],
        ['handle' => 'price', 'type' => 'number'],
        ['handle' => 'topics', 'type' => 'terms', 'config' => ['taxonomy' => 'topics', 'multiple' => true]],
    ]]);
    $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog']);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'hierarchical' => true, 'route' => '/topics/{slug}']);
    $this->topics->collections()->attach($this->blog);
});

function componentPost(string $title, array $data = []): EntryTranslation
{
    return app(PublishEntry::class)->handle(app(CreateEntry::class)->handle(test()->blog, ['title' => $title, 'data' => $data]));
}

function entriesTitles(string $props, array $data = []): string
{
    return trim(Blade::render('<x-sunrice-cms::entries collection="blog" '.$props.'>@foreach ($component->entries as $e){{ $e->title }} @endforeach</x-sunrice-cms::entries>', $data));
}

it('takes where rows with operators, terms by slug with their children, and a page name', function () {
    $news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    $local = app(SaveTerm::class)->handle($this->topics, ['title' => 'Local', 'parent_id' => $news->id]);
    $tips = app(SaveTerm::class)->handle($this->topics, ['title' => 'Tips']);
    componentPost('Alpha', ['city' => 'Jakarta', 'price' => 10, 'topics' => [$news->id]]);
    componentPost('Beta', ['city' => 'Bandung', 'price' => 50, 'topics' => [$local->id]]);
    componentPost('Gamma', ['city' => 'Medan', 'price' => 200, 'topics' => [$tips->id]]);

    expect(entriesTitles(':where="[[\'price\', \'<\', 100]]" order="alphabetical"'))->toBe('Alpha Beta')
        ->and(entriesTitles(':where="[[\'city\', \'in\', [\'Jakarta\', \'Medan\']]]" order="price"'))->toBe('Alpha Gamma')
        ->and(entriesTitles(':where="[[\'city\', \'Bandung\'], \'price\' => 50]"'))->toBe('Beta')
        ->and(entriesTitles(':terms="[\'topics\' => \'news\']" order="alphabetical"'))->toBe('Alpha Beta')
        ->and(entriesTitles('terms="topics:local,tips" order="alphabetical"'))->toBe('Beta Gamma')
        ->and(entriesTitles('search="gam"'))->toBe('Gamma')
        ->and(entriesTitles('order="-price,title"'))->toBe('Gamma Beta Alpha')
        ->and(fn () => entriesTitles(':where="[[\'price\']]"'))->toThrow(Exception::class, 'Give "where" rows');

    // Each list reads its own page number, and keeps the rest of the query string.
    request()->query->set('news', '2');
    request()->query->set('q', 'x');
    $html = Blade::render('<x-sunrice-cms::entries collection="blog" :paginate="2" page-name="news" order="alphabetical">@foreach ($component->entries as $e){{ $e->title }} @endforeach|{{ $component->entries->url(1) }}</x-sunrice-cms::entries>');
    expect($html)->toContain('Gamma |')->toContain('q=x')->toContain('news=1');
});

it('takes terms under a parent, in an order, up to a limit, and tells the term shown', function () {
    $news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    $zebra = app(SaveTerm::class)->handle($this->topics, ['title' => 'Zebra', 'parent_id' => $news->id]);
    app(SaveTerm::class)->handle($this->topics, ['title' => 'Apple', 'parent_id' => $news->id]);
    app(SaveTerm::class)->handle($this->topics, ['title' => 'Tips']);
    componentPost('One', ['topics' => [$zebra->id]]);
    componentPost('Two', ['topics' => [$zebra->id]]);
    $render = fn (string $props) => trim(Blade::render('<x-sunrice-cms::terms taxonomy="topics" '.$props.'>@foreach ($component->terms as $t){{ $t->title() }}{{ $component->isCurrent($t) ? \'*\' : \'\' }} @endforeach</x-sunrice-cms::terms>'));

    expect($render('parent="news"'))->toBe('Zebra Apple')
        ->and($render(':parent="'.$news->id.'" order-by="title"'))->toBe('Apple Zebra')
        ->and($render('parent="top"'))->toBe('News Tips')
        ->and($render('parent="nope"'))->toBe('')
        ->and($render('order-by="-entries" :limit="1"'))->toBe('Zebra')
        ->and($render('order-by="-title" :limit="2"'))->toBe('Zebra Tips')
        ->and($render('tree parent="news" order-by="title"'))->toBe('Apple Zebra');

    (new Page(Page::TERM, 'en', 'cms.term', term: $zebra->translations()->firstOrFail()))->share();
    expect($render('parent="news"'))->toBe('Zebra* Apple');
});

it('lets the template set the SEO title, description and noindex, or describe a listing page', function () {
    componentPost('Hello');
    $render = fn (string $props, array $data = []) => Blade::render('<x-sunrice-cms::seo '.$props.' />', $data);

    expect($render('title="Contact" description="Write <b>to</b> us"'))
        ->toContain('<title>Contact | Acme</title>')->toContain('<meta name="description" content="Write to us">')
        ->not->toContain('noindex');
    expect($render('default-title="Shop" noindex'))->toContain('<title>Shop | Acme</title>')->toContain('content="noindex, nofollow"');
    expect($render('collection="blog"'))->toContain('<title>Blog | Acme</title>')->toContain('<link rel="canonical" href="'.url('/blog').'">');
    expect($render(':collection="$blog"', ['blog' => $this->blog]))->toContain('<title>Blog | Acme</title>');

    // The page's own SEO title wins over default-title, never over title.
    $own = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->blog, ['title' => 'Own', 'seo' => ['title' => 'Own title']]));
    expect($render(':entry="$e" default-title="Fallback"', ['e' => $own]))->toContain('<title>Own title | Acme</title>')
        ->and($render(':entry="$e" title="Forced"', ['e' => $own]))->toContain('<title>Forced | Acme</title>');
    get('/blog/hello')->assertOk();
});

it('takes the search box\'s query string name and the results\' page name', function () {
    componentPost('Coffee beans');
    request()->query->set('find', 'coffee');

    $html = Blade::render('<x-sunrice-cms::search results name="find" page-name="p">{{ $component->name }}|{{ $component->query }}|@foreach ($component->results as $r){{ $r->title }}@endforeach|{{ $component->results->getPageName() }}</x-sunrice-cms::search>');

    expect($html)->toBe('find|coffee|Coffee beans|p');
});
