<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Actions\Media\UploadMedia;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Menu;
use Sunrice\Cms\Settings\SiteSettings;
use Sunrice\StarterKit\Actions\InstallStarter;
use Sunrice\StarterKit\Actions\MakeStarter;
use Sunrice\StarterKit\Installation;
use Sunrice\StarterKit\Starter;
use Sunrice\StarterKit\Starters;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Storage::fake('public');
    $this->made = sys_get_temp_dir().'/sunrice-starter-'.uniqid();
});

// The starter's files land in the test app: gone after each test.
afterEach(function () {
    $files = new Filesystem;
    $files->deleteDirectory(lang_path('en'));
    $files->deleteDirectory(lang_path('vendor'));
    foreach ([resource_path('views/cms'), resource_path('views/errors'), public_path('cms'), $this->made] as $directory) {
        $files->deleteDirectory($directory);
    }
});

function blogStarter(): Starter
{
    return app(Starters::class)->find('blog') ?? throw new RuntimeException('No blog starter.');
}

it('lists the starters, the kit\'s blog among them', function () {
    artisan('sunrice:starters')->expectsTable(['Name', 'Title', 'Description', 'Languages'], [
        ['blog', 'Blog', 'A small blog: pages, posts with topics and a quote block, a main menu and a footer, with the CMS\'s own templates.', 'en'],
    ])->assertSuccessful();

    expect(blogStarter()->title)->toBe('Blog')
        ->and(blogStarter()->locales)->toBe(['en'])
        ->and(blogStarter()->mediaFiles())->toHaveCount(2);
});

it('changes nothing on a dry run, saying what it would do', function () {
    artisan('sunrice:starter', ['name' => 'blog', '--dry-run' => true])
        ->expectsOutputToContain('dry run: nothing saved')
        ->expectsOutputToContain('2 to upload')
        ->assertSuccessful();

    expect(Collection::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(is_file(resource_path('views/cms/layout.blade.php')))->toBeFalse();
});

it('installs a whole site: structure, content with its dates, media with alt text, templates and assets', function () {
    artisan('sunrice:starter', ['name' => 'blog'])->expectsOutputToContain('installed')->assertSuccessful();

    expect(Collection::query()->orderBy('handle')->pluck('handle')->all())->toBe(['pages', 'posts'])
        ->and(Menu::query()->where('handle', 'main')->firstOrFail()->items()->count())->toBe(3);
    $hello = EntryTranslation::query()->where('draft_slug', 'hello-world')->sole();
    $morning = Media::query()->where('filename', 'morning.jpg')->sole();
    expect($hello->isLive())->toBeTrue()
        ->and($hello->published_at?->toDateString())->toBe('2026-01-10')
        ->and($hello->data['image'])->toBe($morning->id)
        ->and($morning->alt)->toBe('A warm sunrise over a calm horizon')
        ->and(EntryTranslation::query()->where('draft_slug', 'a-draft')->sole()->isPublished())->toBeFalse();
    expect(is_file(resource_path('views/cms/layout.blade.php')))->toBeTrue()
        ->and(is_file(resource_path('views/cms/blocks/quote.blade.php')))->toBeTrue()
        ->and(is_file(public_path('cms/site.css')))->toBeTrue();

    // The site shows it with the starter's templates: its own block view, the menu and the footer.
    get('/blog/hello-world')->assertOk()
        ->assertSee('class="block-quote"', false)->assertSee('Start where you are.')
        ->assertSee('Made with Sunrice.');
});

it('shows the CMS\'s language switcher in the blog\'s layout, once the site has another language', function () {
    artisan('sunrice:starter', ['name' => 'blog'])->assertSuccessful();
    get('/blog/hello-world')->assertOk()->assertDontSee('aria-label="Languages"', false);

    config(['sunrice-cms.locales' => ['en', 'id']]);
    get('/blog/hello-world')->assertOk()
        ->assertSee('<nav aria-label="Languages" class="language-switcher">', false)
        ->assertSee('<span aria-current="true" lang="en">English</span>', false)
        // Not translated yet: the English post at the Indonesian address.
        ->assertSee('<a href="'.url('/id/blog/hello-world').'" hreflang="id" lang="id" data-fallback>Bahasa Indonesia</a>', false);
    expect((string) file_get_contents(public_path('cms/site.css')))->toContain('.language-switcher [aria-current="true"]');
});

it('adds only what\'s missing when run again, keeping files changed since unless forced', function () {
    $install = app(InstallStarter::class);
    $install->handle(blogStarter());
    file_put_contents(resource_path('views/cms/collections/show.blade.php'), 'mine');

    $again = $install->handle(blogStarter());
    expect($again->files(Installation::KEPT))->toBe(['resources/views/cms/collections/show.blade.php'])
        ->and($again->files(Installation::COPIED))->toBe([])
        ->and($again->uploaded)->toBe([])->and($again->reused)->toBe(['evening.jpg', 'morning.jpg'])
        ->and(Media::query()->count())->toBe(2)
        ->and(Entry::query()->count())->toBe(5)
        ->and(file_get_contents(resource_path('views/cms/collections/show.blade.php')))->toBe('mine');

    $forced = $install->handle(blogStarter(), force: true);
    expect($forced->files(Installation::COPIED))->toBe(['resources/views/cms/collections/show.blade.php'])
        ->and(file_get_contents(resource_path('views/cms/collections/show.blade.php')))->not->toBe('mine');
});

it('installs the structure alone, without its entries, terms and global values', function () {
    artisan('sunrice:starter', ['name' => 'blog', '--no-content' => true])->assertSuccessful();

    expect(Collection::query()->count())->toBe(2)
        ->and(Entry::query()->count())->toBe(0)
        ->and(InstallStarter::withoutContent(['entries' => [], 'terms' => [], 'globals' => [['handle' => 'footer', 'values' => []]]]))
        ->toBe(['globals' => [['handle' => 'footer']]]);
});

it('makes a starter from a site, which installs on another as it was', function () {
    app(InstallStarter::class)->handle(blogStarter());
    mkdir(public_path('cms/fonts'), recursive: true);
    file_put_contents(public_path('cms/fonts/x.woff2'), 'font');

    artisan('sunrice:make-starter', ['name' => 'my-blog', '--to' => $this->made, '--title' => 'My blog', '--public' => ['cms']])
        ->expectsOutputToContain('Wrote the starter "My blog"')->assertSuccessful();

    $made = Starter::load($this->made);
    $site = $made->site();
    $hello = collect($site['entries'])->firstWhere('slug', 'hello-world');
    expect($made->name)->toBe('my-blog')
        ->and($hello['translations']['en']['data']['image'])->toBe(['media' => 'morning.jpg'])
        ->and(array_map(basename(...), $made->mediaFiles()))->toBe(['evening.jpg', 'morning.jpg'])
        ->and($made->mediaDetails()['morning.jpg']['alt'])->toBe('A warm sunrise over a calm horizon')
        ->and(is_file("{$this->made}/views/cms/blocks/quote.blade.php"))->toBeTrue()
        ->and(is_file("{$this->made}/public/cms/fonts/x.woff2"))->toBeTrue();

    // On this site everything is there already; a folder that isn't empty is refused without --force.
    $again = app(InstallStarter::class)->handle($made);
    expect($again->files(Installation::COPIED))->toBe([])->and($again->uploaded)->toBe([]);
    artisan('sunrice:make-starter', ['name' => 'my-blog', '--to' => $this->made])->expectsOutputToContain('isn\'t empty')->assertFailed();
    artisan('sunrice:make-starter', ['name' => 'My Blog', '--to' => $this->made.'-x'])->expectsOutputToContain('lowercase letters')->assertFailed();
    artisan('sunrice:make-starter', ['name' => 'x', '--to' => $this->made.'-x', '--public' => ['nope']])->expectsOutputToContain('"nope" isn\'t a folder of public/')->assertFailed();
    expect(is_dir($this->made.'-x'))->toBeFalse();
});

it('takes the app\'s translation files, the site\'s settings and the files they use, which install on a fresh app', function () {
    app(InstallStarter::class)->handle(blogStarter());
    $logo = app(UploadMedia::class)->handle(UploadedFile::fake()->image('logo.png', 20, 20));
    $about = EntryTranslation::query()->where('draft_slug', 'about')->sole();
    app(SaveSettings::class)->handle(new SiteSettings, ['name' => 'Morning Notes', 'description' => 'Notes at sunrise.', 'logo' => $logo->id, 'homepage' => $about->entry_id,
        'publisherType' => 'NewsMediaOrganization', 'sameAs' => 'https://example.com/notes', 'noindex' => true]);
    (new Filesystem)->ensureDirectoryExists(lang_path('en'));
    file_put_contents(lang_path('en/site.php'), "<?php\n\nreturn ['tagline' => 'Slow mornings'];\n");
    (new Filesystem)->ensureDirectoryExists(lang_path('vendor/sunrice-admin/en'));
    file_put_contents(lang_path('vendor/sunrice-admin/en/admin.php'), "<?php\n\nreturn [];\n");

    app(MakeStarter::class)->handle('morning', $this->made);
    $settings = Starter::load($this->made)->site()['settings']['site'];
    expect($settings)->toMatchArray(['name' => 'Morning Notes', 'logo' => ['media' => 'logo.png'], 'homepage' => ['entry' => 'pages/about']])
        ->and($settings)->not->toHaveKey('noindex')
        ->and(is_file("{$this->made}/media/logo.png"))->toBeTrue()
        ->and(is_file("{$this->made}/lang/en/site.php"))->toBeTrue()
        ->and(is_dir("{$this->made}/lang/vendor/sunrice-admin"))->toBeFalse();

    // A fresh app: no content, settings, media or translation files of its own.
    Schema::withoutForeignKeyConstraints(function (): void {
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            if (str_starts_with($table, 'sunrice_')) {
                DB::table($table)->delete();
            }
        }
    });
    Cache::flush();
    app()->forgetScopedInstances();
    Storage::fake('public');
    (new Filesystem)->deleteDirectory(lang_path('en'));

    app(InstallStarter::class)->handle(Starter::load($this->made));

    $site = new SiteSettings;
    expect($site->name)->toBe('Morning Notes')
        ->and($site->description)->toBe('Notes at sunrise.')
        ->and($site->publisherType)->toBe('NewsMediaOrganization')
        ->and($site->logo)->toBe(Media::query()->where('filename', 'logo.png')->sole()->id)
        ->and($site->homepage)->toBe(EntryTranslation::query()->where('draft_slug', 'about')->sole()->entry_id)
        ->and($site->noindex)->toBeFalse()
        ->and(require lang_path('en/site.php'))->toBe(['tagline' => 'Slow mornings']);
    get('/')->assertOk()->assertSee('Morning Notes');
});

it('says what\'s wrong with a starter it can\'t install', function () {
    artisan('sunrice:starter', ['name' => 'nope'])->expectsOutputToContain('There is no starter "nope"')->assertFailed();
    artisan('sunrice:starter')->expectsOutputToContain('Name a starter')->assertFailed();
    artisan('sunrice:starter', ['--path' => sys_get_temp_dir()])->expectsOutputToContain('has no starter.json')->assertFailed();

    expect(fn () => (new Starters)->register(blogStarter()->path)->register(blogStarter()->path.'/')->all())->not->toThrow(InvalidArgumentException::class)
        ->and(Collection::query()->count())->toBe(0);
});

it('reads the database cache store a few times per page of the site', function () {
    config(['cache.default' => 'database', 'cache.stores.database.connection' => null]);
    app('cache')->forgetDriver('database');
    artisan('sunrice:starter', ['name' => 'blog'])->assertSuccessful();

    foreach (['/blog' => 4, '/blog/hello-world' => 6, '/about' => 5] as $path => $budget) {
        get($path)->assertOk(); // warms the cache
        app()->forgetScopedInstances(); // a new request, as in production
        DB::flushQueryLog();
        DB::enableQueryLog();
        get($path)->assertOk();
        $reads = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains(str_replace(['"', '`'], '', $query['query']).' ', ' cache '));
        DB::disableQueryLog();

        // The panel's settings, the content versions, what every page reads, then the page's own (its address, share image, the menus).
        expect(count($reads))->toBeLessThanOrEqual($budget, $path);
    }
});
