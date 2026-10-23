<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\CreateTranslation;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Types\TermsField;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\Term;
use Sunrice\Cms\Support\TermParents;

use function Pest\Laravel\get;

/*
 * A term's title() and translation() default to the language of the page
 * shown (Locales::current()), as its url() does, falling back to the main
 * language's; admin code names the language it shows.
 */
beforeEach(function () {
    config(['sunrice-cms.locales' => ['en', 'id', 'zh']]);
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Blueprint::query()->create(['handle' => 'book', 'title' => 'Book', 'fields' => []]);
    $this->topics = Taxonomy::query()->create(['handle' => 'topics', 'title' => 'Topics', 'route' => '/topics/{slug}', 'hierarchical' => true]);
    $this->books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'blueprint' => 'book', 'route' => '/books/{slug}']);
    $this->topics->collections()->attach($this->books->id, ['term_pages' => false, 'index_page' => false]);
    $this->news = app(SaveTerm::class)->handle($this->topics, ['title' => 'News']);
    app(SaveTermContent::class)->handle($this->news, ['title' => 'Berita', 'slug' => 'berita'], 'id');
    // Only in English.
    $this->tips = app(SaveTerm::class)->handle($this->topics, ['title' => 'Tips', 'parent_id' => $this->news->id]);
});

function topic(int $id): Term
{
    return Term::query()->with('translations')->findOrFail($id);
}

it('gives the title in the page\'s language by default, as url() does', function () {
    app()->setLocale('id');

    expect(topic($this->news->id)->title())->toBe('Berita')
        ->and(topic($this->news->id)->translation()?->slug)->toBe('berita')
        ->and(topic($this->news->id)->url())->toBe(url('/id/topics/berita'));

    app()->setLocale('en');
    expect(topic($this->news->id)->title())->toBe('News');
});

it('falls back to the main language\'s title without a translation', function () {
    app()->setLocale('id');
    expect(topic($this->tips->id)->title())->toBe('Tips')
        ->and(topic($this->tips->id)->translation()?->locale)->toBe('en');

    app()->setLocale('zh');
    expect(topic($this->news->id)->title())->toBe('News');

    // A locale that isn't a content language (the panel's, say): the main one.
    app()->setLocale('fr');
    expect(topic($this->news->id)->title())->toBe('News');
});

it('still takes the language asked for', function () {
    app()->setLocale('id');
    expect(topic($this->news->id)->title('en'))->toBe('News')
        ->and(topic($this->news->id)->translation('en')?->slug)->toBe('news');

    app()->setLocale('en');
    expect(topic($this->news->id)->title('id'))->toBe('Berita')
        ->and(topic($this->news->id)->title('zh'))->toBe('News');
});

it('shows translated term names next to their translated links on a translated page', function () {
    $hello = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->books, ['title' => 'Hello', 'terms' => ['topics' => [$this->news->id, $this->tips->id]]]));
    app(PublishEntry::class)->handle(app(SaveDraft::class)->handle(app(CreateTranslation::class)->handle($hello->entry, 'id'), ['title' => 'Halo', 'slug' => 'halo']));
    $views = sys_get_temp_dir().'/sunrice-term-titles-'.uniqid();
    mkdir("{$views}/cms/collections/books", recursive: true);
    file_put_contents("{$views}/cms/collections/books/show.blade.php", '@foreach ($entry->termsOf(\'topics\') as $t)[{{ $t->title() }}]@endforeach'
        .'<x-sunrice-cms::terms taxonomy="topics">@foreach ($component->terms as $t)({{ $t->title() }})@endforeach</x-sunrice-cms::terms>');
    View::addLocation($views);

    get('/id/books/halo')->assertOk()->assertSee('[Berita][Tips]', false)->assertSee('(Berita)', false)->assertDontSee('News');
    get('/books/hello')->assertOk()->assertSee('[News][Tips]', false)->assertSee('(News)', false)->assertDontSee('Berita');
});

it('keeps the main language in the admin\'s term labels, whatever the panel\'s language', function () {
    app()->setLocale('id');

    expect(TermsField::labels([$this->news->id]))->toBe([$this->news->id => 'News'])
        ->and(array_column(TermsField::options($this->topics)['items'], 'label'))->toBe(['News', 'Tips'])
        ->and(TermParents::of([$this->tips->id]))->toBe([$this->tips->id => ['News']]);
});
