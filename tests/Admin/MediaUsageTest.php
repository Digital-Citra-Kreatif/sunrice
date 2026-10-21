<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Actions\Media\DeleteMedia;
use Sunrice\Admin\Actions\Media\OptimizeImage;
use Sunrice\Admin\Actions\Media\ReplaceMedia;
use Sunrice\Admin\Actions\Media\RestoreOriginalImage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaUsage;
use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\BrandingSettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\delete;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    usePanel();
});

function usedFile(string $name = 'photo.jpg', int $width = 400, int $height = 300): Media
{
    return app(UploadMedia::class)->handle(UploadedFile::fake()->image($name, $width, $height));
}

it('keeps where files are used, per item and language', function () {
    [$a, $b] = [usedFile(), usedFile('b.jpg')];
    $usages = app(MediaUsages::class);

    $usages->sync('shop.product', '7', [$a->id, $b->id, $a->id, 999], 'Product: Kettle', '/admin/products/7/edit');
    $usages->sync('shop.product', '8', [$a->id], 'Product: Pan');
    expect($usages->of($a))->toBe([
        ['type' => 'shop.product', 'label' => 'Product: Kettle', 'url' => '/admin/products/7/edit'],
        ['type' => 'shop.product', 'label' => 'Product: Pan', 'url' => null],
    ]);

    // A new save replaces what the item used.
    $usages->sync('shop.product', '7', [$b->id], 'Product: Kettle');
    expect(count($usages->of($a)))->toBe(1)->and(count($usages->of($b)))->toBe(1);

    $usages->sync('shop.product', '8', [$b->id], 'Produk: Panci', null, 'id');
    $usages->forget('shop.product', '8', 'id');
    expect(count($usages->of($b)))->toBe(1);
    $usages->forget('shop.product', '8');
    expect(MediaUsage::query()->where('item_id', '8')->count())->toBe(0);
});

it('asks before deleting a file in use, and shows where', function () {
    $file = usedFile();
    app(MediaUsages::class)->sync('shop.product', '7', [$file->id], 'Product: Kettle');
    $file->delete();

    expect(fn () => app(DeleteMedia::class)->handle($file))->toThrow(ValidationException::class, 'photo.jpg is used by Product: Kettle');

    actingAs(adminUser(Panel::ACCESS, 'sunrice.media.view', 'sunrice.media.delete'));
    getJson("/admin/media/{$file->id}/usages")->assertJson(['usages' => [['label' => 'Product: Kettle']]]);
    delete("/admin/media/{$file->id}/force")->assertSessionHasErrors('media');
    post('/admin/media/bulk', ['action' => 'delete', 'ids' => [$file->id]])->assertSessionHas('success', '0 files deleted for good. 1 in use was kept: open it to see where, and delete it from there.');
    expect(Media::withTrashed()->find($file->id))->not->toBeNull();

    delete("/admin/media/{$file->id}/force", ['force' => true])->assertSessionHasNoErrors();
    expect(Media::withTrashed()->find($file->id))->toBeNull()->and(MediaUsage::query()->count())->toBe(0);
});

it('records the branding logo, browser icon and sign-in image as used, so they aren\'t deleted by mistake', function () {
    [$logo, $icon, $image] = [usedFile('logo.png'), usedFile('icon.png'), usedFile('login.jpg')];

    app(SaveSettings::class)->handle(app(BrandingSettings::class), ['logo' => $logo->id, 'favicon' => $icon->id, 'loginImage' => $image->id]);
    $used = ['type' => 'sunrice.settings', 'label' => 'Settings: Branding', 'url' => route('sunrice.admin.settings.edit', 'branding')];
    expect(app(MediaUsages::class)->of($logo))->toBe([$used])
        ->and(app(MediaUsages::class)->of($icon))->toBe([$used])
        ->and(app(MediaUsages::class)->of($image))->toBe([$used]);
    expect(fn () => app(DeleteMedia::class)->handle($logo))->toThrow(ValidationException::class);

    // Through the Settings page too; a cleared picker frees the file.
    actingAs(adminUser(Panel::ACCESS, 'sunrice.settings.branding'));
    $this->put(route('sunrice.admin.settings.update', 'branding'), [...app(BrandingSettings::class)->toArray(), 'name' => 'Acme', 'logo' => null])->assertSessionHasNoErrors();
    expect(app(MediaUsages::class)->of($logo->refresh()))->toBe([])
        ->and(app(MediaUsages::class)->of($icon))->toBe([$used]);
});

it('records settings media on every save, and the upgrade backfills what sections hold already', function () {
    $logo = usedFile('logo.png');
    // Stored before usages were recorded (or saved since without a change).
    app(BrandingSettings::class)->fill(['logo' => $logo->id])->save();
    expect(app(MediaUsages::class)->of($logo))->toBe([]);

    // Saved again, unchanged: recorded.
    app(SaveSettings::class)->handle(app(BrandingSettings::class), app(BrandingSettings::class)->toArray());
    expect(app(MediaUsages::class)->of($logo))->toHaveCount(1);

    // The backfill migration records every section's.
    MediaUsage::query()->delete();
    (require dirname(__DIR__, 2).'/packages/admin/database/migrations/2026_01_01_000012_backfill_sunrice_settings_media_usages.php')->up();
    expect(app(MediaUsages::class)->of($logo))->toBe([['type' => 'sunrice.settings', 'label' => 'Settings: Branding', 'url' => route('sunrice.admin.settings.edit', 'branding')]]);
});

it('shrinks big images in place, keeps the original and puts it back', function () {
    $file = usedFile('big.jpg', 3000, 2000);
    $before = $file->size;
    $path = $file->path;

    $result = app(OptimizeImage::class)->handle($file, 1200, 1200, 70);
    $file->refresh();
    expect($result['status'])->toBe('optimized')
        ->and([$file->width, $file->height])->toBe([1200, 800])
        ->and($file->path)->toBe($path)
        ->and($file->version)->toBe(2)
        ->and(Storage::disk('local')->exists('sunrice-originals/'.$path))->toBeTrue();

    // A second run doesn't touch the kept original.
    app(OptimizeImage::class)->handle($file, 600, 600, 70);
    expect(app(RestoreOriginalImage::class)->handle($file->refresh()))->toBeTrue();
    expect([$file->width, $file->height, $file->size])->toBe([3000, 2000, $before])
        ->and(Storage::disk('local')->exists('sunrice-originals/'.$path))->toBeFalse();
});

it('skips what it can\'t make smaller, and forgets the original when replaced', function () {
    $svg = app(UploadMedia::class)->handle(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>'));
    expect(app(OptimizeImage::class)->handle($svg, 100, 100, 70)['status'])->toBe('skipped');

    $file = usedFile('big.jpg', 3000, 2000);
    app(OptimizeImage::class)->handle($file, 1200, 1200, 70);
    app(ReplaceMedia::class)->handle($file->refresh(), UploadedFile::fake()->image('new.jpg', 500, 500));
    expect(Storage::disk('local')->exists('sunrice-originals/'.$file->path))->toBeFalse();
});

it('optimizes from the console, with a dry run and restore', function () {
    $file = usedFile('big.jpg', 3000, 2000);

    artisan('sunrice:optimize-images --dry-run --max-width=1000')->expectsOutputToContain('Would optimize 1 image')->assertSuccessful();
    expect($file->refresh()->width)->toBe(3000);

    artisan('sunrice:optimize-images', ['--max-width' => 1000, '--max-height' => 1000, '--id' => [$file->id]])->expectsOutputToContain('Optimized 1 image')->assertSuccessful();
    expect($file->refresh()->width)->toBe(1000);

    artisan('sunrice:optimize-images --restore')->expectsOutputToContain('Put back 1 original.')->assertSuccessful();
    expect($file->refresh()->width)->toBe(3000);
    artisan('sunrice:optimize-images --quality=500')->assertFailed();
});
