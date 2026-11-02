<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Notifications\TestMail;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Cms\Actions\Entries\CreateEntry;
use Sunrice\Cms\Actions\Entries\PublishEntry;
use Sunrice\Cms\Actions\Settings\MakeSiteImages;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Content;
use Sunrice\Cms\Mail\ReviewSentBack;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Settings\SiteImages;
use Sunrice\Cms\Settings\SiteSettings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

beforeEach(function () {
    Storage::fake('public');
    config(['app.name' => 'Acme', 'app.url' => 'https://acme.test']);
    usePanel(fn (Panel $panel) => $panel->brand('Acme admin')->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS, 'cms.settings.site'));
});

/** A PNG upload drawn with GD: `$color` (rgba hex) on a transparent canvas, or filling it. */
function siteImage(string $name, int $width, int $height, string $color = 'ff0000', bool $transparentEdges = false): Media
{
    $image = (new ImageManager(new Driver))->create($width, $height);
    if ($transparentEdges) {
        $image->drawRectangle((int) ($width / 4), (int) ($height / 4), fn ($r) => $r->size((int) ($width / 2), (int) ($height / 2))->background($color));
    } else {
        $image->fill($color);
    }

    return app(UploadMedia::class)->handle(UploadedFile::fake()->createWithContent($name, (string) $image->toPng()), []);
}

/** @return array{0: int, 1: int} */
function pngSize(string $path): array
{
    $image = (new ImageManager(new Driver))->read(Storage::disk('public')->get($path));

    return [$image->width(), $image->height()];
}

/** The site's <head>, as <x-sunrice-cms::seo> prints it on an app page. */
function siteHead(): string
{
    return Blade::render('<x-sunrice-cms::seo title="Contact" />');
}

it('prints no icon links without a site icon, so public/favicon.ico keeps working', function () {
    expect(siteHead())->not->toContain('rel="icon"')->not->toContain('apple-touch-icon')
        ->and(Content::site()->iconLinks())->toBe([])
        ->and(Content::site()->iconUrl())->toBeNull();
});

it('makes the icon\'s sizes once when it\'s saved, and prints their links', function () {
    $icon = siteImage('icon.png', 600, 600, 'ff000080', transparentEdges: true);
    put('/admin/settings/site', ['name' => 'Acme', 'icon' => $icon->id])->assertSessionHasNoErrors();

    $made = (new SiteImages)->icon;
    expect($made['media'])->toBe($icon->id)
        ->and(array_keys($made['files']))->toBe([16, 32, 180, 192, 512]);
    foreach ($made['files'] as $size => $path) {
        expect($path)->toStartWith(MakeSiteImages::DIRECTORY."/icon-{$icon->id}-{$icon->version}-")
            ->and(pngSize($path))->toBe([(int) $size, (int) $size]);
    }
    // The home screen icon is opaque: white where the image is transparent.
    $apple = (new ImageManager(new Driver))->read(Storage::disk('public')->get($made['files']['180']));
    expect($apple->pickColor(0, 0)->toHex())->toBe('ffffff')
        ->and($apple->pickColor(0, 0)->isTransparent())->toBeFalse();
    // The small ones keep the transparency.
    expect((new ImageManager(new Driver))->read(Storage::disk('public')->get($made['files']['32']))->pickColor(0, 0)->isTransparent())->toBeTrue();

    $url = fn (string $size) => Storage::disk('public')->url($made['files'][$size]);
    expect(siteHead())
        ->toContain('<link rel="icon" type="image/png" sizes="32x32" href="'.$url('32').'">')
        ->toContain('<link rel="icon" type="image/png" sizes="16x16" href="'.$url('16').'">')
        ->toContain('<link rel="apple-touch-icon" sizes="180x180" href="'.$url('180').'">');
    expect(Content::site()->iconUrl(192))->toBe($url('192'))
        ->and(Content::site()->iconUrl())->toBe($url('512'))
        ->and(Content::site()->iconUrl(64))->toBeNull();

    // Saving other settings makes nothing again.
    put('/admin/settings/site', ['name' => 'Acme Homes', 'icon' => $icon->id])->assertSessionHasNoErrors();
    expect((new SiteImages)->icon)->toBe($made);
});

it('gives a changed icon new URLs and deletes the old files, and none when it\'s removed', function () {
    $first = siteImage('first.png', 512, 512);
    put('/admin/settings/site', ['icon' => $first->id])->assertSessionHasNoErrors();
    $old = (new SiteImages)->icon['files'];

    $second = siteImage('second.png', 512, 512, '00ff00');
    put('/admin/settings/site', ['icon' => $second->id])->assertSessionHasNoErrors();
    $new = (new SiteImages)->icon['files'];
    expect(array_intersect($old, $new))->toBe([])
        ->and(siteHead())->toContain(Storage::disk('public')->url($new['32']))->not->toContain(Storage::disk('public')->url($old['32']));
    foreach ($old as $path) {
        Storage::disk('public')->assertMissing($path);
    }
    // The same image again (its file replaced, a new version): new URLs too.
    $second->forceFill(['version' => $second->version + 1])->save();
    app(MakeSiteImages::class)->handle();
    expect(array_intersect((new SiteImages)->icon['files'], $new))->toBe([]);
    $current = (new SiteImages)->icon['files'];

    put('/admin/settings/site', ['icon' => null])->assertSessionHasNoErrors();
    expect((new SiteImages)->icon)->toBe([])->and(siteHead())->not->toContain('rel="icon"');
    foreach ($current as $path) {
        Storage::disk('public')->assertMissing($path);
    }
    // The chosen images themselves stay in the library.
    Storage::disk('public')->assertExists($first->path);
});

it('uses an SVG icon as it is', function () {
    $svg = app(UploadMedia::class)->handle(UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>'), []);
    put('/admin/settings/site', ['icon' => $svg->id])->assertSessionHasNoErrors();

    expect(siteHead())->toContain('<link rel="icon" type="image/svg+xml" sizes="any" href="'.Storage::disk('public')->url($svg->path).'">')
        ->not->toContain('apple-touch-icon')
        ->and(Content::site()->iconUrl(32))->toBeNull()
        ->and(Storage::disk('public')->allFiles(MakeSiteImages::DIRECTORY))->toBe([]);
});

it('keeps the logo where it was saved, on Settings → Site, for templates, structured data and emails', function () {
    // Chosen on Settings → SEO before: the same setting, now on Settings → Site.
    $logo = siteImage('logo.png', 800, 200, '1e66f5');
    app(SiteSettings::class)->fill(['logo' => $logo->id])->save();

    get('/admin/settings/site')->assertInertia(fn ($page) => $page
        ->where('values.logo', $logo->id)
        ->where('form', fn ($sections): bool => collect($sections)->flatMap(fn ($section) => $section['fields'])->pluck('name')->intersect(['icon', 'logo'])->count() === 2));
    get('/admin/settings/seo')->assertInertia(fn ($page) => $page
        ->where('structured.values', fn ($values): bool => ! array_key_exists('logo', is_array($values) ? $values : $values->all())));

    expect(Content::site()->logoImage()?->id)->toBe($logo->id);
    $jsonLd = siteHead();
    expect($jsonLd)->toContain('"logo":{"@type":"ImageObject","url":'.json_encode($logo->url(), JSON_UNESCAPED_SLASHES));

    // Saved again: its PNG for emails, twice the 48 pixels it's shown at, its proportions kept.
    put('/admin/settings/site', ['logo' => null])->assertSessionHasNoErrors();
    put('/admin/settings/site', ['logo' => $logo->id])->assertSessionHasNoErrors();
    $email = Content::site()->emailLogo();
    expect($email)->not->toBeNull()
        ->and([$email['width'], $email['height']])->toBe([384, 96])
        ->and($email['url'])->toStartWith('https://acme.test/')->toEndWith('/logo-email.png')
        ->and(pngSize((new SiteImages)->logo['path']))->toBe([384, 96]);
});

it('puts the site\'s logo at the top of the panel\'s and the CMS\'s emails, else its name', function () {
    app(SiteSettings::class)->fill(['name' => 'Kebun Studio'])->save();
    // The panel's own logo doesn't show: the CMS gives the site's brand.
    app(BrandingSettings::class)->fill(['logo' => siteImage('panel.png', 100, 100)->id])->save();
    $collection = Collection::query()->create(['handle' => 'journal', 'title' => 'Journal']);
    $translation = app(PublishEntry::class)->handle(app(CreateEntry::class)->handle($collection, ['title' => 'Our first year']));

    $panelMail = function (): string {
        $message = (new TestMail('Acme admin'))->toMail((object) []);

        return (string) app(Markdown::class)->render($message->markdown, $message->data());
    };
    expect($panelMail())->toMatch('/<td class="header"[^>]*>\s*<a [^>]*>\s*Kebun Studio\s*<\/a>/')->not->toContain('<img')
        // Signed and footed by the site, as the header: not the app's name nor the panel's.
        ->toContain('Kebun Studio</p>')->toContain('© '.date('Y').' Kebun Studio.')->not->toContain('© '.date('Y').' Acme')
        ->and((new ReviewSentBack($translation, 'Rina', null))->render())->toContain('Kebun Studio</div>')->not->toContain('<img');

    // A logo chosen before this version: its PNG is made when the first email needs it.
    $logo = siteImage('logo.png', 400, 200);
    app(SiteSettings::class)->fill(['logo' => $logo->id])->save();
    (new SiteImages)->fill(['logo' => []])->save();

    $png = fn (): string => 'https://acme.test'.Storage::disk('public')->url((new SiteImages)->logo['path']);
    expect($panelMail())->toContain('<img src="'.$png().'" alt="Kebun Studio" width="96" height="48"')->not->toContain('.svg')
        ->and((new ReviewSentBack($translation, 'Rina', null))->render())->toContain('<img src="'.$png().'" alt="Kebun Studio" width="96" height="48"')->not->toContain('<svg');

    // An SVG logo (no Imagick to read it): the name, never the SVG.
    $svg = app(UploadMedia::class)->handle(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 10"><rect width="40" height="10"/></svg>'), []);
    put('/admin/settings/site', ['logo' => $svg->id])->assertSessionHasNoErrors();
    if (! extension_loaded('imagick')) {
        expect($panelMail())->not->toContain('<img')->toContain('Kebun Studio')->not->toContain('.svg');
    }
});
