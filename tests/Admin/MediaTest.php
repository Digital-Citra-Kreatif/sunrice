<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Media\DeleteMedia;
use Sunrice\Admin\Actions\Media\ReplaceMedia;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Jobs\GenerateImageSizes;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\SafeSvg;

use function Pest\Laravel\artisan;

beforeEach(function () {
    Storage::fake('public');
});

function upload(UploadedFile $file, array $attributes = []): Media
{
    return app(UploadMedia::class)->handle($file, $attributes);
}

/** The validation message for an upload that is refused. */
function refused(UploadedFile $file): string
{
    try {
        upload($file);
    } catch (ValidationException $e) {
        return $e->errors()['file'][0];
    }

    throw new RuntimeException('The upload was accepted.');
}

/** An upload whose type is read from its content (fake uploads go by the name). */
function realUpload(string $name, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

function svg(string $body): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">'.$body.'</svg>');
}

it('stores an upload under a new name, with its dimensions and image sizes', function () {
    $media = upload(UploadedFile::fake()->image('Summer Photo.jpg', 1200, 800), ['uploaded_by' => '7']);

    expect($media->path)->toMatch('#^media/\d{4}/\d{2}/[a-z0-9]{10}-summer-photo\.jpg$#')
        ->and($media->filename)->toBe('Summer Photo.jpg')
        ->and($media->title)->toBe('Summer Photo')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and([$media->width, $media->height])->toBe([1200, 800])
        ->and($media->uploaded_by)->toBe('7')
        ->and(array_keys($media->sizes))->toEqualCanonicalizing(['thumbnail', 'medium', 'large']);
    Storage::disk('public')->assertExists($media->files());

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($media->sizes['thumbnail']));
    expect([$width, $height])->toBe([300, 300]);
    [$width] = getimagesizefromstring(Storage::disk('public')->get($media->sizes['medium']));
    expect($width)->toBe(800);
    // Never larger than the original.
    [$width] = getimagesizefromstring(Storage::disk('public')->get($media->sizes['large']));
    expect($width)->toBe(1200);

    expect($media->url())->toEndWith($media->path.'?v=1')
        ->and($media->url('thumbnail'))->toEndWith($media->sizes['thumbnail'].'?v=1')
        ->and($media->url('nope'))->toEndWith($media->path.'?v=1');
});

it('refuses files that could run code, whatever they are called', function () {
    expect(refused(UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')))->toContain('.php files can\'t be uploaded')
        ->and(refused(UploadedFile::fake()->createWithContent('page.html', '<html></html>')))->toContain('.html files')
        ->and(refused(realUpload('notes.txt', '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>')))->toContain('a web page or a script')
        ->and(refused(UploadedFile::fake()->createWithContent('noext', 'x')))->toContain('Files without an extension');

    config(['sunrice-admin.media.allowed_extensions' => ['jpg', 'php']]);
    expect(refused(UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;')))->toContain('Allowed: jpg.');

    expect(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses SVGs with scripts and keeps clean ones as they are', function () {
    foreach ([
        '<script>alert(1)</script>',
        '<rect onload="alert(1)"/>',
        '<a xlink:href="java&#x09;script:alert(1)"><rect/></a>',
        '<foreignObject><div/></foreignObject>',
        '<animate attributeName="href" values="javascript:alert(1)"/>',
        '<style>@import url(https://evil.test/x.css);</style>',
    ] as $body) {
        expect(SafeSvg::isSafe('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'.$body.'</svg>'))->toBeFalse();
    }
    expect(SafeSvg::isSafe('<!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg"/>'))->toBeFalse()
        ->and(SafeSvg::isSafe('<html><script/></html>'))->toBeFalse()
        ->and(refused(svg('<script>alert(1)</script>')))->toContain('scripts or links');

    $media = upload(svg('<circle cx="5" cy="5" r="4" fill="#f80"/><a href="#x"><rect width="1" height="1"/></a>'));
    expect($media->mime_type)->toBe('image/svg+xml')
        ->and($media->sizes)->toBe([])
        ->and($media->width)->toBeNull();
});

it('refuses files over the size limit', function () {
    config(['sunrice-admin.media.max_upload_kb' => 10]);

    expect(refused(UploadedFile::fake()->create('big.pdf', 11, 'application/pdf')))->toBe('The file is larger than the 10 KB limit.');
});

it('replaces a file in place, bumping the version and remaking its sizes', function () {
    $media = upload(UploadedFile::fake()->image('photo.jpg', 1000, 1000));
    $path = $media->path;

    $media = app(ReplaceMedia::class)->handle($media, UploadedFile::fake()->image('new.jpg', 500, 400));
    expect($media->path)->toBe($path)
        ->and($media->version)->toBe(2)
        ->and([$media->width, $media->height])->toBe([500, 400])
        ->and($media->url())->toEndWith('?v=2');
    Storage::disk('public')->assertExists($media->files());

    $media = app(ReplaceMedia::class)->handle($media, UploadedFile::fake()->image('new.png', 50, 50));
    expect($media->path)->toBe(substr($path, 0, -3).'png')
        ->and($media->mime_type)->toBe('image/png');
    Storage::disk('public')->assertMissing($path);
    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing($media->files());
});

it('trashes, and deletes for good with its files', function () {
    $media = upload(UploadedFile::fake()->image('photo.jpg', 600, 600));
    $files = $media->files();

    $media->delete();
    expect(Media::query()->count())->toBe(0)->and(Media::withTrashed()->count())->toBe(1);
    Storage::disk('public')->assertExists($files);

    app(DeleteMedia::class)->handle($media);
    expect(Media::withTrashed()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('makes sizes on the queue when asked, and again from the console', function () {
    Queue::fake();
    config(['sunrice-admin.media.queue' => true]);
    $media = upload(UploadedFile::fake()->image('photo.jpg', 900, 600));

    expect($media->sizes)->toBe([]);
    Queue::assertPushed(GenerateImageSizes::class, fn (GenerateImageSizes $job) => $job->mediaId === $media->id);

    config(['sunrice-admin.media.image_sizes' => ['square' => [100, 100, 'crop']]]);
    artisan('sunrice:media-sizes')->expectsOutputToContain('Image sizes made for 1 image.')->assertSuccessful();
    expect(array_keys($media->fresh()->sizes))->toBe(['square']);

    // Sizes that are no longer configured are deleted.
    config(['sunrice-admin.media.image_sizes' => ['wide' => [400, null, 'fit']]]);
    artisan('sunrice:media-sizes', ['--id' => [$media->id]])->assertSuccessful();
    expect(array_keys($media->fresh()->sizes))->toBe(['wide']);
    expect(Storage::disk('public')->allFiles())->toEqualCanonicalizing($media->fresh()->files());
});

it('keeps only the original of images it can\'t read or that are too large', function () {
    $broken = upload(UploadedFile::fake()->createWithContent('broken.jpg', 'not really a jpeg'));
    expect($broken->sizes)->toBe([]);

    config(['sunrice-admin.media.max_pixels' => 1000]);
    expect(upload(UploadedFile::fake()->image('big.png', 100, 100))->sizes)->toBe([]);
});
