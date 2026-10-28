<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Sunrice\Admin\Media\Media;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Fields\CustomField;
use Sunrice\Cms\Fields\FieldTypes;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\CollectionListing;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\Taxonomy;
use Sunrice\Cms\Models\TaxonomyListing;
use Sunrice\Cms\Support\Templates;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/*
 * sunrice:make-templates writes, from the blueprints, the files the site
 * picks for each page, and what it writes renders.
 */

const BOOK_PAGES = [
    'collections/books/show.blade.php',
    'collections/books/index.blade.php',
    'collections/books/tags/show.blade.php',
    'collections/books/tags/index.blade.php',
    'collections/authors/show.blade.php',
    'taxonomies/genres/show.blade.php',
    'taxonomies/genres/index.blade.php',
];

function cmsTemplate(string $relative): string
{
    return Templates::directory().'/'.$relative;
}

beforeEach(function () {
    expect(is_dir(Templates::directory()))->toBeFalse(Templates::directory().' exists before the test: remove it.');
    // A custom field built on a built-in type prints as that type.
    app(FieldTypes::class)->register(new class extends CustomField
    {
        public function type(): string
        {
            return 'rating';
        }

        public function baseType(): string
        {
            return 'number';
        }

        public function preset(): array
        {
            return ['min' => 1, 'max' => 5, 'suffix' => ' stars'];
        }
    });

    Fieldset::query()->create(['handle' => 'quote_block', 'title' => 'Quote', 'fields' => [['handle' => 'quote', 'type' => 'textarea']]]);
    Fieldset::query()->create(['handle' => 'hero_block', 'title' => 'Hero', 'fields' => [
        ['handle' => 'heading', 'type' => 'text'],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'photo', 'type' => 'media'],
        ['handle' => 'inner', 'type' => 'flexible', 'config' => ['blocks' => ['quote_block']]],
    ]]);
    Blueprint::query()->create(['handle' => 'book', 'title' => 'Book', 'fields' => [
        ['handle' => 'subtitle', 'type' => 'text'],
        ['handle' => 'summary', 'type' => 'textarea'],
        ['handle' => 'body', 'type' => 'rich_text'],
        ['handle' => 'snippet', 'type' => 'code', 'config' => ['language' => 'html']],
        ['handle' => 'pages', 'type' => 'number', 'label' => 'Pages', 'config' => ['suffix' => ' pages']],
        ['handle' => 'released', 'type' => 'date', 'label' => 'Released'],
        ['handle' => 'format', 'type' => 'select', 'label' => 'Format', 'config' => ['options' => [['value' => 'hardcover', 'label' => 'Hard cover'], ['value' => 'paperback', 'label' => 'Paperback']]]],
        ['handle' => 'signed', 'type' => 'toggle', 'label' => 'Signed'],
        ['handle' => 'website', 'type' => 'link'],
        ['handle' => 'cover', 'type' => 'media'],
        ['handle' => 'gallery', 'type' => 'media', 'config' => ['multiple' => true]],
        ['handle' => 'author', 'type' => 'entries', 'label' => 'Author', 'config' => ['collections' => ['authors']]],
        ['handle' => 'related', 'type' => 'entries', 'label' => 'Related', 'config' => ['collections' => ['books'], 'multiple' => true]],
        ['handle' => 'genre', 'type' => 'terms', 'label' => 'Main genre', 'config' => ['taxonomy' => 'genres']],
        ['handle' => 'stars', 'type' => 'rating', 'label' => 'Stars'],
        ['handle' => 'details', 'type' => 'group', 'fields' => [['handle' => 'isbn', 'type' => 'text'], ['handle' => 'year', 'type' => 'number', 'label' => 'Year']]],
        ['handle' => 'chapters', 'type' => 'repeater', 'fields' => [['handle' => 'name', 'type' => 'text'], ['handle' => 'notes', 'type' => 'repeater', 'fields' => [['handle' => 'note', 'type' => 'text']]]]],
        ['handle' => 'sections', 'type' => 'flexible', 'config' => ['blocks' => ['hero_block', 'quote_block']]],
    ]]);
    Blueprint::query()->create(['handle' => 'review', 'title' => 'Review', 'fields' => [['handle' => 'verdict', 'type' => 'text']]]);
    Blueprint::query()->create(['handle' => 'books_listing', 'title' => 'Books listing', 'fields' => [['handle' => 'intro', 'type' => 'rich_text']]]);
    Blueprint::query()->create(['handle' => 'genres_index', 'title' => 'Genres index', 'fields' => [['handle' => 'lead', 'type' => 'text']]]);

    $this->genres = Taxonomy::query()->create(['handle' => 'genres', 'title' => 'Genres', 'hierarchical' => true, 'route' => '/genres/{slug}', 'index_route' => '/genres', 'index_blueprint' => 'genres_index']);
    $this->tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags']);
    $this->books = Collection::query()->create(['handle' => 'books', 'title' => 'Books', 'blueprint' => 'book', 'blueprints' => ['review'], 'route' => '/books/{slug}',
        'listing_route' => '/books', 'listing_blueprint' => 'books_listing', 'dated' => true]);
    $this->authors = Collection::query()->create(['handle' => 'authors', 'title' => 'Authors', 'route' => '/authors/{slug}']);
    Collection::query()->create(['handle' => 'notes', 'title' => 'Notes']);
    $this->books->taxonomies()->attach($this->tags->id, ['term_pages' => true, 'index_page' => true]);
    $this->books->taxonomies()->attach($this->genres->id, ['term_pages' => false, 'index_page' => false]);
    Collection::query()->create(['handle' => 'old', 'title' => 'Old', 'route' => '/old/{slug}'])->delete();
    Taxonomy::query()->create(['handle' => 'gone', 'title' => 'Gone', 'route' => '/gone/{slug}'])->delete();
});

afterEach(function () {
    if (getenv('KEEP_TEMPLATES') === false) {
        File::deleteDirectory(Templates::directory());
    }
});

it('writes the template of every page each record has, in the files the site picks', function () {
    artisan('sunrice:make-templates', ['--all' => true])->assertSuccessful();

    foreach (BOOK_PAGES as $path) {
        expect(cmsTemplate($path))->toBeFile();
    }
    // Shared: each kind of block's view (and of the blocks in those blocks), the term tree, the starter layout.
    foreach (['blocks/hero_block.blade.php', 'blocks/quote_block.blade.php', 'partials/term-tree.blade.php', 'layout.blade.php', 'partials/menu.blade.php'] as $path) {
        expect(cmsTemplate($path))->toBeFile();
    }
    // No pages: no files. Trashed records are left out.
    expect(cmsTemplate('collections/authors/index.blade.php'))->not->toBeFile()
        ->and(cmsTemplate('collections/notes'))->not->toBeDirectory()
        ->and(cmsTemplate('taxonomies/tags'))->not->toBeDirectory()
        ->and(cmsTemplate('collections/books/genres'))->not->toBeDirectory()
        ->and(cmsTemplate('collections/old'))->not->toBeDirectory()
        ->and(cmsTemplate('taxonomies/gone'))->not->toBeDirectory();

    // Every file compiles.
    foreach (File::allFiles(Templates::directory()) as $file) {
        expect(Templates::syntaxError($file->getContents()))->toBeNull($file->getRelativePathname());
    }
    expect(File::get(cmsTemplate('collections/books/show.blade.php')))
        ->toContain("@extends('cms.layout')")
        ->toContain("@section('content')")
        ->toContain('Written by php artisan sunrice:make-templates from the blueprints "Book" (book), "Review" (review).')
        ->toContain('Books: Entry page (/books/{slug})')
        ->toContain("@switch(\$entry->entry->blueprintHandle())\n");
});

it('writes templates that render every field, and nothing for empty ones', function () {
    actingAs(adminUser());
    $sizes = ['width' => 1200, 'height' => 800];
    $cover = Media::query()->create(['disk' => 'public', 'path' => 'media/cover.jpg', 'filename' => 'cover.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'alt' => 'The cover', 'caption' => 'Cover art', ...$sizes]);
    $shot = Media::query()->create(['disk' => 'public', 'path' => 'media/shot.jpg', 'filename' => 'shot.jpg', 'mime_type' => 'image/jpeg', 'size' => 1, 'alt' => 'A shot']);
    $pdf = Media::query()->create(['disk' => 'public', 'path' => 'media/sample.pdf', 'filename' => 'sample.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'title' => 'Sample chapter']);
    $fiction = app(SaveTerm::class)->handle($this->genres, ['title' => 'Fiction']);
    $fantasy = app(SaveTerm::class)->handle($this->genres, ['title' => 'Fantasy', 'parent_id' => $fiction->id]);
    $classic = app(SaveTerm::class)->handle($this->tags, ['title' => 'Classic']);
    $ann = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->authors, ['title' => 'Ann Author', 'slug' => 'ann']));
    $other = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->books, ['title' => 'Other book', 'slug' => 'other']));
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->books, ['title' => 'The <Hobbit>', 'slug' => 'hobbit', 'terms' => ['tags' => [$classic->id], 'genres' => [$fantasy->id]], 'data' => [
        'subtitle' => 'There and back <again>',
        'summary' => "A hobbit's tale\nin two lines",
        'body' => '<p>The <strong>body</strong></p>',
        'snippet' => '<b>hi</b>',
        'pages' => 310,
        'released' => '1937-09-21',
        'format' => 'hardcover',
        'signed' => true,
        'website' => ['type' => 'url', 'url' => 'https://example.com/hobbit', 'label' => 'Publisher', 'new_tab' => true],
        'cover' => $cover->id,
        'gallery' => [$shot->id, $pdf->id],
        'author' => $ann->entry_id,
        'related' => [$other->entry_id],
        'genre' => $fiction->id,
        'stars' => 5,
        'details' => ['isbn' => '978-0-00-000000-0', 'year' => 1937],
        'chapters' => [['name' => 'An Unexpected Party', 'notes' => [['note' => 'Bilbo at home']]], ['name' => 'Roast Mutton', 'notes' => []]],
        'sections' => [
            ['id' => 'b1', 'type' => 'hero_block', 'key' => 'top', 'hidden' => false, 'values' => ['heading' => 'In a hole', 'body' => '<p>Hero <em>text</em></p>', 'photo' => $shot->id,
                'inner' => [['id' => 'b2', 'type' => 'quote_block', 'hidden' => false, 'values' => ['quote' => 'Inner quote']]]]],
            ['id' => 'b3', 'type' => 'quote_block', 'hidden' => false, 'values' => ['quote' => 'Not all who wander']],
        ],
    ]]));
    // An entry with the other blueprint, and an empty one.
    $review = app(CreateEntry::class)->handle($this->books, ['title' => 'A review', 'slug' => 'review']);
    $review->entry->forceFill(['blueprint' => 'review'])->save();
    app(SaveDraft::class)->handle($review->refresh(), ['title' => 'A review', 'slug' => 'review', 'data' => ['verdict' => 'Splendid']]);
    app(PublishEntry::class)->handle($review->refresh());
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($this->books, ['title' => 'Blank', 'slug' => 'blank']));
    CollectionListing::query()->create(['collection_id' => $this->books->id, 'locale' => 'en', 'data' => ['intro' => '<p>All our <em>books</em></p>']]);
    TaxonomyListing::query()->create(['taxonomy_id' => $this->genres->id, 'locale' => 'en', 'data' => ['lead' => 'Every genre']]);
    auth()->logout();

    artisan('sunrice:make-templates', ['--all' => true])->assertSuccessful();

    $page = get('/books/hobbit')->assertOk()->assertViewIs('cms.collections.books.show')
        ->assertSee('<h1>The &lt;Hobbit&gt;</h1>', false)
        ->assertSee('<time datetime="', false)
        ->assertSee('There and back &lt;again&gt;', false)
        ->assertSee("A hobbit&#039;s tale<br />\nin two lines", false)
        ->assertSee('<div class="prose field field-body"><p>The <strong>body</strong></p></div>', false)
        ->assertSee('<pre><code class="language-html">&lt;b&gt;hi&lt;/b&gt;</code></pre>', false)
        ->assertSee('310 pages')
        ->assertSee('21 September 1937')
        ->assertSee('Hard cover')
        ->assertSee('<span class="field-label">Signed:</span> Yes', false)
        ->assertSee('<a href="https://example.com/hobbit" target="_blank" rel="noopener">Publisher</a>', false)
        ->assertSee('alt="The cover"', false)
        ->assertSee('width="1200" height="800"', false)
        ->assertSee('<figcaption>Cover art</figcaption>', false)
        ->assertSee('alt="A shot"', false)
        ->assertSee('>Sample chapter</a>', false)
        ->assertSee('<a href="'.url('/authors/ann').'">Ann Author</a>', false)
        ->assertSee('<a href="'.url('/books/other').'">Other book</a>', false)
        ->assertSee('<a href="'.url('/genres/fiction').'">Fiction</a>', false)
        ->assertSee('5 stars')
        ->assertSee('978-0-00-000000-0')
        ->assertSee('<span class="field-label">Year:</span> 1937', false)
        ->assertSee('An Unexpected Party')
        ->assertSee('Bilbo at home')
        ->assertSee('Roast Mutton')
        ->assertSee('<section class="block block-hero_block" id="top">', false)
        ->assertSee('In a hole')
        ->assertSee('Hero <em>text</em>', false)
        ->assertSee('Inner quote')
        ->assertSee('Not all who wander')
        // The entry's terms, by taxonomy, linking to their pages.
        ->assertSee('<a href="'.url('/books/tags/classic').'">Classic</a>', false)
        ->assertSee('Fantasy')
        // The layout (written as the starter one, since the site had none).
        ->assertSee('<a class="skip-link" href="#main">', false);
    expect($page->getContent())->not->toContain('Splendid');

    // The other blueprint's fields, on its entries.
    get('/books/review')->assertOk()->assertSee('Splendid')->assertDontSee('field-subtitle', false);
    // Empty fields print nothing.
    $blank = get('/books/blank')->assertOk()->assertSee('<h1>Blank</h1>', false)->getContent();
    // (A toggle is never empty: off reads "No".)
    expect(str_replace('<p class="field field-signed"><span class="field-label">Signed:</span> No</p>', '', $blank))->not->toContain('class="field ')->not->toContain('class="group')->not->toContain('<section class="block')->not->toContain('class="rows');

    get('/books')->assertOk()->assertViewIs('cms.collections.books.index')
        ->assertSee('All our <em>books</em>', false)
        ->assertSee('<li class="card">', false)
        ->assertSee('<a href="'.url('/books/hobbit').'">The &lt;Hobbit&gt;</a>', false)
        ->assertSee('src="'.$cover->url('medium').'"', false)
        ->assertSee('A hobbit&#039;s tale', false);
    get('/books/tags/classic')->assertOk()->assertViewIs('cms.collections.books.tags.show')
        ->assertSee('<h1>Classic</h1>', false)->assertSee('The &lt;Hobbit&gt;', false);
    get('/books/tags')->assertOk()->assertViewIs('cms.collections.books.tags.index')
        ->assertSee('<a href="'.url('/books/tags/classic').'">Classic</a>', false);
    // The hierarchical taxonomy's shared pages: a term's entries (those under it too) and the tree.
    get('/genres/fiction')->assertOk()->assertViewIs('cms.taxonomies.genres.show')->assertSee('The &lt;Hobbit&gt;', false);
    $index = get('/genres')->assertOk()->assertViewIs('cms.taxonomies.genres.index')->assertSee('Every genre')->getContent();
    expect($index)->toMatch('~<a href="'.preg_quote(url('/genres/fiction'), '~').'">Fiction</a>\s*<span class="muted">\(\d+\)</span>\s*<ul class="terms">\s*<li>\s*<a href="'.preg_quote(url('/genres/fantasy'), '~').'">Fantasy</a>~');
    get('/authors/ann')->assertOk()->assertViewIs('cms.collections.authors.show')->assertSee('<h1>Ann Author</h1>', false);

    expect(EntryTranslation::query()->count())->toBe(5);
});

it('keeps the files that exist, and overwrites the pages\' own with --force', function () {
    File::ensureDirectoryExists(cmsTemplate('collections/books'));
    File::put(cmsTemplate('collections/books/show.blade.php'), 'mine');
    File::ensureDirectoryExists(cmsTemplate('blocks'));
    File::put(cmsTemplate('blocks/quote_block.blade.php'), 'my quote');

    artisan('sunrice:make-templates', ['--all' => true])
        ->expectsOutputToContain('skip (exists)')
        ->assertSuccessful();
    expect(File::get(cmsTemplate('collections/books/show.blade.php')))->toBe('mine')
        ->and(File::get(cmsTemplate('collections/books/index.blade.php')))->toContain("@extends('cms.layout')");

    artisan('sunrice:make-templates', ['--all' => true, '--force' => true])->expectsOutputToContain('overwrite')->assertSuccessful();
    expect(File::get(cmsTemplate('collections/books/show.blade.php')))->toContain("@extends('cms.layout')")
        // Shared files are never overwritten: other pages use them.
        ->and(File::get(cmsTemplate('blocks/quote_block.blade.php')))->toBe('my quote');
});

it('writes only the pages of the records named', function () {
    artisan('sunrice:make-templates', ['--collection' => ['authors'], '--no-interaction' => true])->assertSuccessful();
    expect(cmsTemplate('collections/authors/show.blade.php'))->toBeFile()
        ->and(cmsTemplate('collections/books'))->not->toBeDirectory()
        ->and(cmsTemplate('taxonomies'))->not->toBeDirectory();

    artisan('sunrice:make-templates', ['--taxonomy' => ['genres'], '--no-interaction' => true])->assertSuccessful();
    expect(cmsTemplate('taxonomies/genres/show.blade.php'))->toBeFile()
        ->and(cmsTemplate('taxonomies/genres/index.blade.php'))->toBeFile()
        ->and(cmsTemplate('collections/books'))->not->toBeDirectory();

    // A trashed or unknown record is refused, and without a choice nothing is written.
    artisan('sunrice:make-templates', ['--collection' => ['old'], '--no-interaction' => true])->expectsOutputToContain('No such collection "old"')->assertFailed();
    artisan('sunrice:make-templates', ['--taxonomy' => ['nope'], '--all' => true])->expectsOutputToContain('No such taxonomy "nope"')->assertFailed();
    artisan('sunrice:make-templates', ['--no-interaction' => true])->expectsOutputToContain('Nothing chosen: pass --all')->assertFailed();
    expect(cmsTemplate('collections/books'))->not->toBeDirectory();
});

it('asks which pages of each record, then shows what it will write and asks first', function () {
    File::ensureDirectoryExists(cmsTemplate('collections/books'));
    File::put(cmsTemplate('collections/books/index.blade.php'), 'mine');

    artisan('sunrice:make-templates')
        ->expectsChoice('Authors (authors)', ['collections/authors/show.blade.php'], [
            'collections/authors/show.blade.php' => 'Entry page (/authors/{slug}) → cms/collections/authors/show.blade.php',
        ])
        ->expectsChoice('Books (books)', ['collections/books/show.blade.php', 'collections/books/index.blade.php'], [
            'collections/books/show.blade.php' => 'Entry page (/books/{slug}) → cms/collections/books/show.blade.php',
            'collections/books/index.blade.php' => 'Listing page (/books) → cms/collections/books/index.blade.php (exists, will be skipped)',
            'collections/books/tags/show.blade.php' => 'Tags term pages (/books/tags/{slug}) → cms/collections/books/tags/show.blade.php',
            'collections/books/tags/index.blade.php' => 'Tags index page (/books/tags) → cms/collections/books/tags/index.blade.php',
        ])
        ->expectsChoice('Genres (genres)', [], [
            'taxonomies/genres/show.blade.php' => 'Term pages (/genres/{slug}) → cms/taxonomies/genres/show.blade.php',
            'taxonomies/genres/index.blade.php' => 'Index page (/genres) → cms/taxonomies/genres/index.blade.php',
        ])
        ->expectsTable(['File', 'For', 'Action'], [
            ['cms/collections/authors/show.blade.php', 'Authors: Entry page', 'write'],
            ['cms/collections/books/show.blade.php', 'Books: Entry page', 'write'],
            ['cms/collections/books/index.blade.php', 'Books: Listing page', 'skip (exists)'],
            ['cms/blocks/hero_block.blade.php', 'Block: Hero', 'write'],
            ['cms/blocks/quote_block.blade.php', 'Block: Quote', 'write'],
            ['cms/layout.blade.php', 'Layout (the starter templates\' one)', 'write'],
            ['cms/partials/menu.blade.php', 'Menu partial (the layout\'s)', 'write'],
        ])
        ->expectsConfirmation('Write these files?', 'yes')
        ->expectsOutputToContain('6 written, 1 skipped')
        ->assertSuccessful();
    expect(cmsTemplate('collections/books/show.blade.php'))->toBeFile()
        ->and(File::get(cmsTemplate('collections/books/index.blade.php')))->toBe('mine')
        ->and(cmsTemplate('taxonomies'))->not->toBeDirectory();

    // Saying no writes nothing.
    File::deleteDirectory(Templates::directory());
    artisan('sunrice:make-templates')
        ->expectsChoice('Authors (authors)', ['collections/authors/show.blade.php'], ['collections/authors/show.blade.php' => 'Entry page (/authors/{slug}) → cms/collections/authors/show.blade.php'])
        ->expectsChoice('Books (books)', [], [
            'collections/books/show.blade.php' => 'Entry page (/books/{slug}) → cms/collections/books/show.blade.php',
            'collections/books/index.blade.php' => 'Listing page (/books) → cms/collections/books/index.blade.php',
            'collections/books/tags/show.blade.php' => 'Tags term pages (/books/tags/{slug}) → cms/collections/books/tags/show.blade.php',
            'collections/books/tags/index.blade.php' => 'Tags index page (/books/tags) → cms/collections/books/tags/index.blade.php',
        ])
        ->expectsChoice('Genres (genres)', [], [
            'taxonomies/genres/show.blade.php' => 'Term pages (/genres/{slug}) → cms/taxonomies/genres/show.blade.php',
            'taxonomies/genres/index.blade.php' => 'Index page (/genres) → cms/taxonomies/genres/index.blade.php',
        ])
        ->expectsConfirmation('Write these files?', 'no')
        ->expectsOutputToContain('Nothing written.')
        ->assertSuccessful();
    expect(is_dir(Templates::directory()))->toBeFalse();
});
