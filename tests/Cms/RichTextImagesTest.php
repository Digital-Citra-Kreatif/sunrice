<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Entries\SaveDraft;
use Sunrice\Cms\Actions\Globals\SaveGlobals;
use Sunrice\Cms\Actions\Terms\SaveTerm;
use Sunrice\Cms\Actions\Terms\SaveTermContent;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content as Cms;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\HydrationContext;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Fieldset;
use Sunrice\Cms\Models\GlobalSet;
use Sunrice\Cms\Models\Taxonomy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 * Rich text's media library images get srcset, sizes, width, height and
 * lazy loading when templates get the values: never in what's stored.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en']]);
});

/** An image in the library, 2400 × 1600, with its sizes made. */
function richImage(string $mime = 'image/jpeg', ?array $sizes = null, ?int $width = 2400, ?int $height = 1600, string $name = 'photo'): Media
{
    $name .= '-'.uniqid();

    return Media::query()->create([
        'disk' => 'public', 'path' => "media/{$name}.jpg", 'filename' => "{$name}.jpg", 'mime_type' => $mime, 'size' => 1,
        'width' => $width, 'height' => $height,
        'sizes' => $sizes ?? ['thumbnail' => "media/{$name}-thumbnail.jpg", 'medium' => "media/{$name}-medium.jpg", 'large' => "media/{$name}-large.jpg"],
    ]);
}

/** Rich text with this image as the editor stores it (sanitised). */
function richHtml(Media $media, string $class = 'sunrice-img sunrice-img-w-50 sunrice-img-left', string $extra = ''): string
{
    return Schema::make([Field::make('body', 'rich_text')])->normalize(['body' => '<p>Before</p><p><img src="'.e($media->url()).'" alt="A &quot;saw&quot;" class="'.$class.'" data-media-id="'.$media->id.'"'.$extra.'></p><p>After &amp; more</p>'])['body'];
}

function richHydrate(string $html, ?HydrationContext $context = null): string
{
    return Schema::make([Field::make('body', 'rich_text')])->hydrate(['body' => $html], $context ?? new HydrationContext('en'))['body'];
}

/** The attributes added to a stored image tag (the rest is byte for byte as stored). */
function addedAttributes(string $stored, string $hydrated): string
{
    preg_match('/<img[^>]*?\s?\/?>/', $stored, $before);
    $start = (string) substr($before[0], 0, -3);
    expect($hydrated)->toContain($start)->and(str_replace($before[0], '', $stored))->toBe(preg_replace('/<img[^>]*>/', '', $hydrated));
    preg_match('/<img[^>]*>/', $hydrated, $after);

    return trim(substr($after[0], strlen($start), -2));
}

it('gives a library image its sizes, dimensions and lazy loading, keeping everything stored', function () {
    $media = richImage();
    $stored = richHtml($media);

    expect($stored)->toContain('class="sunrice-img sunrice-img-w-50 sunrice-img-left"')
        ->and(addedAttributes($stored, richHydrate($stored)))->toBe(
            'srcset="'.e($media->srcset()).'" sizes="(min-width: 46rem) 23rem, (min-width: 40em) 50vw, 100vw" width="2400" height="1600" loading="lazy" decoding="async"'
        )
        ->and($media->srcset())->toContain('-medium.jpg?v=1 800w, ')->toContain('-large.jpg?v=1 1600w, ')->not->toContain('thumbnail')
        // src, alt and class as stored: the browser picks from srcset.
        ->and(richHydrate($stored))->toContain('src="'.str_replace('=', '&#61;', $media->url()).'" alt="A &#34;saw&#34;" class="sunrice-img sunrice-img-w-50 sunrice-img-left" data-media-id="'.$media->id.'"');
});

it('works sizes out from the width and alignment presets and the text\'s width', function (string $class, string $sizes) {
    $media = richImage();

    expect(richHydrate(richHtml($media, $class)))->toContain(' sizes="'.$sizes.'"');
})->with([
    'a width' => ['sunrice-img sunrice-img-w-75', '(min-width: 46rem) 34.5rem, 75vw'],
    'centred' => ['sunrice-img sunrice-img-w-25 sunrice-img-center', '(min-width: 46rem) 11.5rem, 25vw'],
    'full' => ['sunrice-img sunrice-img-full', '(min-width: 46rem) 46rem, 100vw'],
    'floated right' => ['sunrice-img sunrice-img-w-25 sunrice-img-right', '(min-width: 46rem) 11.5rem, (min-width: 40em) 25vw, 100vw'],
    'floated, no width (capped at 50%)' => ['sunrice-img sunrice-img-left', '(min-width: 46rem) 23rem, (min-width: 40em) 50vw, 100vw'],
    'floated at full width' => ['sunrice-img sunrice-img-w-100 sunrice-img-left', '(min-width: 46rem) 46rem, 100vw'],
    'no preset' => ['', '(min-width: 46rem) 46rem, 100vw'],
]);

it('makes a captioned image responsive, its sizes from the presets on its figure, the figure and caption as stored', function () {
    config(['sunrice-admin.rich_text.image_classes' => ['rounded' => 'Rounded']]);
    $media = richImage();
    $stored = Schema::make([Field::make('body', 'rich_text')])->normalize(['body' => '<p>Before</p><figure class="sunrice-img sunrice-img-w-25 sunrice-img-right evil">'
        .'<img src="'.e($media->url()).'" alt="A saw" class="rounded junk" data-media-id="'.$media->id.'"><figcaption>An <em>old</em> saw</figcaption></figure><p>After</p>'])['body'];
    $hydrated = richHydrate($stored);

    expect($stored)->toContain('<figure class="sunrice-img sunrice-img-w-25 sunrice-img-right"><img ')->toContain('class="rounded"')->not->toContain('evil')->not->toContain('junk')
        ->and(addedAttributes($stored, $hydrated))->toBe(
            'srcset="'.e($media->srcset()).'" sizes="(min-width: 46rem) 11.5rem, (min-width: 40em) 25vw, 100vw" width="2400" height="1600" loading="lazy" decoding="async"'
        )
        ->and($hydrated)->toContain('<figure class="sunrice-img sunrice-img-w-25 sunrice-img-right"><img ')->toContain('<figcaption>An <em>old</em> saw</figcaption></figure><p>After</p>')
        // A figure without presets: the image's own classes count, as for an image without a caption.
        ->and(richHydrate(str_replace(' class="sunrice-img sunrice-img-w-25 sunrice-img-right"', '', $stored)))->toContain(' sizes="(min-width: 46rem) 46rem, 100vw"');
});

it('takes the text\'s width or one sizes for all from the config', function () {
    $stored = richHtml(richImage(), 'sunrice-img sunrice-img-w-50');

    config(['sunrice-cms.rich_text.content_width' => 720]);
    expect(richHydrate($stored))->toContain(' sizes="(min-width: 720px) 360px, 50vw"');
    config(['sunrice-cms.rich_text.content_width' => '60em']);
    expect(richHydrate($stored))->toContain(' sizes="(min-width: 60em) 30em, 50vw"');
    config(['sunrice-cms.rich_text.content_width' => 'wide']);
    expect(richHydrate($stored))->toContain(' sizes="50vw"');
    config(['sunrice-cms.rich_text.sizes' => '(min-width: 60rem) 40rem, 100vw']);
    expect(richHydrate($stored))->toContain(' sizes="(min-width: 60rem) 40rem, 100vw"');
});

it('keeps the stored width and height, adding the missing one in proportion', function () {
    $media = richImage();

    expect(richHydrate(richHtml($media, extra: ' width="600" height="100"')))->toContain('width="600" height="100"')->not->toContain('width="2400"')->not->toContain('height="400"')
        ->and(richHydrate(richHtml($media, extra: ' width="600"')))->toContain('width="600"')->toContain(' height="400"')->not->toContain('width="2400"')
        ->and(richHydrate(richHtml($media, extra: ' height="300"')))->toContain('height="300"')->toContain(' width="450"')->not->toContain('height="1600"');
});

it('leaves missing, trashed, non-image and SVG files, external images and images with their own srcset or loading alone', function () {
    $trashed = richImage();
    $trashedHtml = richHtml($trashed);
    $trashed->delete();
    $html = [
        'missing' => str_replace('data-media-id="', 'data-media-id="99', richHtml(richImage())),
        'trashed' => $trashedHtml,
        'document' => richHtml(richImage('application/pdf')),
        'svg' => richHtml(richImage('image/svg+xml', sizes: [])),
        'external' => Schema::make([Field::make('body', 'rich_text')])->normalize(['body' => '<p><img src="https://example.com/a.jpg" alt="A" class="sunrice-img sunrice-img-w-50"></p>'])['body'],
        'own srcset' => '<p><img src="/a.jpg" srcset="/a.jpg 1x" data-media-id="'.richImage()->id.'" /></p>',
        'own loading' => '<p><img src="/a.jpg" loading="eager" data-media-id="'.richImage()->id.'" /></p>',
        'not an id' => '<p><img src="/a.jpg" data-media-id="x7" /></p>',
    ];

    foreach ($html as $case => $stored) {
        expect(richHydrate($stored))->toBe($stored, $case);
    }
});

it('never prints a srcset without sizes to choose from', function () {
    $noSizes = richHydrate(richHtml(richImage(sizes: [])));
    $unknownWidth = richHydrate(richHtml(richImage(sizes: [], width: null, height: null)));

    expect($noSizes)->not->toContain('srcset')->not->toContain('sizes=')->toContain('width="2400" height="1600" loading="lazy" decoding="async"')
        ->and($unknownWidth)->not->toContain('srcset')->not->toContain('width=')->toContain(' loading="lazy" decoding="async" />');
});

it('gives the same bytes back for rich text without library images', function () {
    $html = [
        '<h2>Ünïcödé — “quotes” &amp; 😀</h2><p>a &gt; b <strong>bold</strong><br /><a href="/x?a&#61;1&amp;b&#61;2" rel="noopener noreferrer">link</a></p>',
        '<p>Text that names data-media-id="7" as words, no image.</p>',
        '<p><img src="https://example.com/a.jpg" alt="ext" /></p><pre><code>&lt;img data-media-id="3"&gt;</code></pre>',
        '<p>Unclosed <em>markup<p>left as is</table>',
        '',
    ];

    foreach ($html as $stored) {
        expect(richHydrate($stored))->toBe($stored);
    }
});

it('escapes what it adds', function () {
    $media = richImage(sizes: ['medium' => 'media/a"b<c>&d-medium.jpg', 'large' => 'media/a"b-large.jpg']);
    config(['sunrice-cms.rich_text.sizes' => '"><script>alert(1)</script>']);
    $hydrated = richHydrate(richHtml($media));

    expect($hydrated)->not->toContain('<script>')->not->toContain('a"b')->not->toContain('<c>')
        ->toContain('a&quot;b&lt;c&gt;&amp;d-medium.jpg?v=1 800w')
        ->toContain('sizes="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"');
    // Exactly one img tag, its attributes all quoted.
    expect(preg_match_all('/<img\b[^>]*>/', $hydrated))->toBe(1);
});

it('prints rich text as stored with the config off', function () {
    config(['sunrice-cms.rich_text.responsive_images' => false]);
    $stored = richHtml(richImage());

    expect(richHydrate($stored))->toBe($stored);
});

it('covers rich text in groups, repeaters and blocks', function () {
    $media = richImage();
    $stored = richHtml($media);
    Fieldset::query()->create(['handle' => 'text', 'title' => 'Text', 'fields' => [['handle' => 'body', 'type' => 'rich_text']]]);
    $schema = Schema::make([
        Field::fromArray(['handle' => 'group', 'type' => 'group', 'fields' => [['handle' => 'body', 'type' => 'rich_text']]]),
        Field::fromArray(['handle' => 'rows', 'type' => 'repeater', 'fields' => [['handle' => 'body', 'type' => 'rich_text']]]),
        Field::make('blocks', 'flexible', ['blocks' => ['text']]),
    ]);
    $values = $schema->hydrate(['group' => ['body' => $stored], 'rows' => [['body' => $stored]], 'blocks' => [['id' => 'b1', 'type' => 'text', 'values' => ['body' => $stored]]]], new HydrationContext('en'));

    expect($values['group']['body'])->toContain('srcset="'.e($media->srcset()).'"')
        ->and($values['rows'][0]['body'])->toContain('srcset="'.e($media->srcset()).'"')
        ->and($values['blocks'][0]->values['body'])->toContain('srcset="'.e($media->srcset()).'"');
});

describe('on the site', function () {
    beforeEach(function () {
        Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'body', 'type' => 'rich_text']]]);
        $this->blog = Collection::query()->create(['handle' => 'blog', 'title' => 'Blog', 'blueprint' => 'post', 'route' => '/blog/{slug}', 'listing_route' => '/blog', 'per_page' => 50]);
        $views = sys_get_temp_dir().'/sunrice-rich-images-'.uniqid();
        mkdir("{$views}/cms/collections/blog", recursive: true);
        file_put_contents("{$views}/cms/collections/blog/index.blade.php", '@foreach ($entries as $p)<article>{!! $p->values()[\'body\'] ?? \'\' !!}</article>@endforeach');
        View::addLocation($views);
    });

    function richPost(Collection $collection, string $title, string $body, bool $publish = true): EntryTranslation
    {
        $translation = app(CreateEntry::class)->handle($collection, ['title' => $title, 'data' => ['body' => $body]]);

        return $publish ? app(PublishEntry::class)->handle($translation) : $translation;
    }

    it('shows entry pages\' images responsive, storing nothing new', function () {
        $media = richImage();
        $post = richPost($this->blog, 'Hello', richHtml($media));
        $stored = $post->refresh()->data;

        get('/blog/hello')->assertOk()->assertSee('srcset="'.e($media->srcset()).'"', false)->assertSee('loading="lazy"', false);
        expect($post->refresh()->data)->toBe($stored)->and($stored['body'])->not->toContain('srcset');
    });

    it('shows a draft\'s images responsive in its preview', function () {
        $live = richImage();
        $draft = richImage();
        $post = richPost($this->blog, 'Hello', richHtml($live));
        actingAs(adminUser(Panel::ACCESS, 'cms.entries.view', 'cms.entries.update'));
        app(SaveDraft::class)->handle($post->refresh(), ['title' => 'Hello', 'data' => ['body' => richHtml($draft)]]);

        get(URL::temporarySignedRoute('sunrice.cms.preview', now()->addMinutes(30), ['translation' => $post->id]))->assertOk()
            ->assertSee('srcset="'.e($draft->srcset()).'"', false)->assertDontSee(e($live->srcset()), false);
    });

    it('gives Cms::toData() lists the same HTML', function () {
        $media = richImage();
        richPost($this->blog, 'Hello', richHtml($media));

        expect(Cms::toData(Cms::entries('blog')->get(), ['body'])[0]['fields']['body'])->toContain('srcset="'.e($media->srcset()).'"')->toContain('decoding="async"');
    });

    it('covers globals and terms', function () {
        Blueprint::query()->create(['handle' => 'footer', 'title' => 'Footer', 'fields' => [['handle' => 'body', 'type' => 'rich_text']]]);
        $set = GlobalSet::query()->create(['handle' => 'footer', 'title' => 'Footer', 'blueprint' => 'footer']);
        $tags = Taxonomy::query()->create(['handle' => 'tags', 'title' => 'Tags', 'blueprint' => 'footer']);
        $media = richImage();
        app(SaveGlobals::class)->handle($set, ['body' => richHtml($media)]);
        $term = app(SaveTerm::class)->handle($tags, ['title' => 'News']);
        app(SaveTermContent::class)->handle($term, ['data' => ['body' => richHtml($media)]]);

        expect(Cms::global('footer')['body'])->toContain('srcset="'.e($media->srcset()).'"')
            ->and($term->refresh()->translations->first()?->values()['body'] ?? '')->toContain('srcset="'.e($media->srcset()).'"');
    });

    it('costs the same queries for a listing of many entries with images as for a few', function () {
        $make = function (int $count): void {
            foreach (range(1, $count) as $i) {
                richPost($this->blog, 'Post '.uniqid(), richHtml(richImage()).richHtml(richImage(), 'sunrice-img sunrice-img-w-75'));
            }
        };
        $queries = function (): array {
            get('/blog')->assertOk(); // warm the site cache
            DB::flushQueryLog();
            DB::enableQueryLog();
            $html = (string) get('/blog')->assertOk()->getContent();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$count, substr_count($html, ' srcset="')];
        };

        $make(2);
        [$few, $fewImages] = $queries();
        $make(30);
        [$many, $manyImages] = $queries();

        expect($fewImages)->toBe(4)->and($manyImages)->toBe(64)->and($many)->toBe($few);
    });
});
