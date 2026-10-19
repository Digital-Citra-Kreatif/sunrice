<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Media\MediaFolder;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\MediaPicker;
use Sunrice\Tests\Admin\Fixtures\BrandSettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

beforeEach(function () {
    Storage::fake('public');
    usePanel(fn (Panel $panel) => $panel->settings(BrandSettings::class));
});

function pickable(string $name, ?MediaFolder $folder = null): Media
{
    $file = str_ends_with($name, '.pdf') ? UploadedFile::fake()->create($name, 5, 'application/pdf') : UploadedFile::fake()->image($name, 200, 200);

    return app(UploadMedia::class)->handle($file, ['folder_id' => $folder?->id]);
}

it('stores picked media ids, only of existing media of the allowed types', function () {
    $logo = pickable('logo.png');
    $photo = pickable('photo.jpg');
    $menu = pickable('menu.pdf');
    $gone = pickable('gone.jpg');
    $gone->delete();
    actingAs(adminUser(Panel::ACCESS, 'settings.brand'));

    get('/admin/settings/brand')->assertInertia(fn (Assert $page) => $page
        ->where('form.0.fields.0', fn ($field) => $field['type'] === 'media' && $field['multiple'] === false && $field['types'] === ['image'])
        ->where('form.0.fields.1.max', 2)
        ->where('values', ['logo' => null, 'gallery' => []]));

    put('/admin/settings/brand', ['logo' => $menu->id, 'gallery' => [$photo->id, $gone->id]])->assertSessionHasErrors(['logo', 'gallery.1']);
    put('/admin/settings/brand', ['logo' => $logo->id, 'gallery' => [$photo->id, $logo->id, $photo->id]])->assertSessionHasErrors(['gallery']);
    put('/admin/settings/brand', ['logo' => 999, 'gallery' => []])->assertSessionHasErrors(['logo']);

    put('/admin/settings/brand', ['logo' => (string) $logo->id, 'gallery' => [$photo->id, $logo->id]])->assertSessionHasNoErrors();
    expect(app(BrandSettings::class)->logo)->toBe($logo->id)
        ->and(app(BrandSettings::class)->gallery)->toBe([$photo->id, $logo->id]);
    get('/admin/settings/brand')->assertInertia(fn (Assert $page) => $page->where('values', ['logo' => $logo->id, 'gallery' => [$photo->id, $logo->id]]));

    expect(fn () => MediaPicker::make('x')->types(['pictures']))->toThrow(InvalidArgumentException::class, 'unknown types pictures');
});

it('gives the picker media by id in order, and pages of a folder or search', function () {
    $trips = MediaFolder::query()->create(['name' => 'Trips']);
    $a = pickable('a.jpg');
    $b = pickable('b.jpg', $trips);
    $menu = pickable('menu.pdf');
    actingAs(adminUser(Panel::ACCESS));
    getJson('/admin/media/picker')->assertForbidden();

    actingAs(adminUser(Panel::ACCESS, 'sunrice.media.view'));
    getJson("/admin/media/picker?ids[]={$b->id}&ids[]=999&ids[]={$a->id}")->assertOk()
        ->assertJsonPath('items.0.id', $b->id)->assertJsonPath('items.1.id', $a->id)->assertJsonCount(2, 'items');

    getJson('/admin/media/picker?types[]=image')->assertOk()
        ->assertJsonPath('folders.0.name', 'Trips')
        ->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $a->id)
        ->assertJsonPath('canUpload', false)
        ->assertJsonPath('nextPage', null);
    getJson("/admin/media/picker?folder={$trips->id}")->assertJsonPath('path.0.name', 'Trips')->assertJsonPath('items.0.id', $b->id);
    getJson('/admin/media/picker?search=menu')->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $menu->id)->assertJsonPath('folders', []);
    getJson('/admin/media/picker?types[]=nope')->assertUnprocessable();
});

it('uploads from the picker and answers with the new media', function () {
    actingAs(adminUser(Panel::ACCESS, 'sunrice.media.view', 'sunrice.media.upload'));

    postJson('/admin/media', ['file' => UploadedFile::fake()->image('new.png', 100, 100)])->assertCreated()
        ->assertJsonPath('title', 'new')->assertJsonPath('mimeType', 'image/png');
    postJson('/admin/media', ['file' => UploadedFile::fake()->createWithContent('x.php', '<?php')])->assertUnprocessable()->assertJsonValidationErrors('file');

    // The page itself still gets a redirect.
    post('/admin/media', ['file' => UploadedFile::fake()->image('page.png')])->assertRedirect();
});
