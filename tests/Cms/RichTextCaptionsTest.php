<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\Reference;
use Sunrice\Cms\Fields\Schema;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Structure\StructureExporter;
use Sunrice\Cms\Structure\StructureImporter;
use Sunrice\Cms\Support\HtmlSanitizer;

/*
 * Captioned images in rich text: <figure class="presets"><img class="styles" …><figcaption>…</figcaption></figure>.
 */

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    config(['sunrice-cms.locales' => ['en'], 'sunrice-admin.rich_text.image_classes' => ['rounded' => 'Rounded']]);
});

/** A captioned image as the editor saves it. */
function captioned(int $mediaId, string $caption = 'An <em>old</em> saw'): string
{
    return '<figure class="sunrice-img sunrice-img-w-50 sunrice-img-left"><img src="/storage/media/saw.jpg" alt="A saw" class="rounded" data-media-id="'.$mediaId.'"><figcaption>'.$caption.'</figcaption></figure>';
}

it('keeps a captioned image as the editor saves it, and the same when saved again', function () {
    $once = HtmlSanitizer::sanitize('<p>Before</p>'.captioned(7).'<p>After</p>');

    expect($once)->toBe('<p>Before</p><figure class="sunrice-img sunrice-img-w-50 sunrice-img-left"><img src="/storage/media/saw.jpg" alt="A saw" class="rounded" data-media-id="7" /><figcaption>An <em>old</em> saw</figcaption></figure><p>After</p>')
        ->and(HtmlSanitizer::sanitize($once))->toBe($once)
        // An image without a caption is kept as it always was.
        ->and(HtmlSanitizer::sanitize('<img src="/a.jpg" class="sunrice-img sunrice-img-w-50 rounded" data-media-id="7">'))
        ->toBe('<img src="/a.jpg" class="sunrice-img sunrice-img-w-50 rounded" data-media-id="7" />');
});

it('holds a figure\'s class to the image classes and cleans its caption', function () {
    $sanitized = HtmlSanitizer::sanitize('<figure class="sunrice-img fixed inset-0 sunrice-img-right" style="position:fixed" onclick="x()"><img src="/a.jpg" class="junk rounded">'
        .'<figcaption onmouseover="x()">Photo by <a href="javascript:alert(1)">me</a> <a href="https://example.com">them</a><script>alert(1)</script><iframe src="https://example.com"></iframe></figcaption></figure>');

    expect($sanitized)->toContain('<figure class="sunrice-img sunrice-img-right"><img src="/a.jpg" class="rounded" />')
        ->toContain('<figcaption>Photo by <a rel="noopener noreferrer">me</a> <a href="https://example.com" rel="noopener noreferrer">them</a></figcaption>')
        ->not->toContain('fixed')->not->toContain('inset-0')->not->toContain('junk')->not->toContain('style')
        ->not->toContain('onclick')->not->toContain('onmouseover')->not->toContain('script')->not->toContain('iframe')->not->toContain('javascript')
        // A figure from elsewhere, its classes not the editor's: a plain figure.
        ->and(HtmlSanitizer::sanitize('<figure class="wp-block-image size-large"><img src="/a.jpg"><figcaption>Hi</figcaption></figure>'))
        ->toBe('<figure><img src="/a.jpg" /><figcaption>Hi</figcaption></figure>');
});

it('tells the media library where a captioned image is used, and carries it through a structure export and import', function () {
    Storage::fake('public');
    $photo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('saw.jpg'));
    Blueprint::query()->create(['handle' => 'post', 'title' => 'Post', 'fields' => [['handle' => 'body', 'type' => 'rich_text']]]);
    $posts = Collection::query()->create(['handle' => 'posts', 'title' => 'Posts', 'blueprint' => 'post', 'route' => '/posts/{slug}']);
    app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($posts, ['title' => 'Hello', 'data' => ['body' => '<p>Text</p>'.captioned($photo->id)]]));

    expect(app(MediaUsages::class)->of($photo))->toHaveCount(1)
        ->and(app(MediaUsages::class)->of($photo)[0]['label'])->toBe('Posts: Hello (EN)')
        ->and(collect(Schema::make([Field::make('body', 'rich_text')])->references(['body' => captioned(5).captioned(9)]))->map(fn ($reference) => [$reference->type, $reference->id])->all())
        ->toBe([[Reference::MEDIA, 5], [Reference::MEDIA, 9]]);

    $file = json_decode(json_encode(app(StructureExporter::class)->export(content: true), JSON_THROW_ON_ERROR), true);
    $exported = collect($file['entries'])->firstWhere('slug', 'hello')['translations']['en']['data']['body'];
    expect($exported)->toContain('<figure class="sunrice-img sunrice-img-w-50 sunrice-img-left"><img ')->toContain('data-media-id="'.$photo->id.'"')
        ->toContain('<figcaption>An <em>old</em> saw</figcaption></figure>');

    Entry::withTrashed()->get()->each->forceDelete();
    EntryTranslation::query()->delete();
    app(StructureImporter::class)->import($file);

    expect(EntryTranslation::query()->where('draft_slug', 'hello')->sole()->draft_data['body'])->toBe($exported);
});
