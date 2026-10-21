<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Models\Menu;
use Sunrice\StarterKit\Actions\InstallStarter;
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

it('adds only what\'s missing when run again, keeping files changed since unless forced', function () {
    $install = app(InstallStarter::class);
    $install->handle(blogStarter());
    file_put_contents(resource_path('views/cms/entry.blade.php'), 'mine');

    $again = $install->handle(blogStarter());
    expect($again->files(Installation::KEPT))->toBe(['resources/views/cms/entry.blade.php'])
        ->and($again->files(Installation::COPIED))->toBe([])
        ->and($again->uploaded)->toBe([])->and($again->reused)->toBe(['evening.jpg', 'morning.jpg'])
        ->and(Media::query()->count())->toBe(2)
        ->and(Entry::query()->count())->toBe(5)
        ->and(file_get_contents(resource_path('views/cms/entry.blade.php')))->toBe('mine');

    $forced = $install->handle(blogStarter(), force: true);
    expect($forced->files(Installation::COPIED))->toBe(['resources/views/cms/entry.blade.php'])
        ->and(file_get_contents(resource_path('views/cms/entry.blade.php')))->not->toBe('mine');
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

it('says what\'s wrong with a starter it can\'t install', function () {
    artisan('sunrice:starter', ['name' => 'nope'])->expectsOutputToContain('There is no starter "nope"')->assertFailed();
    artisan('sunrice:starter')->expectsOutputToContain('Name a starter')->assertFailed();
    artisan('sunrice:starter', ['--path' => sys_get_temp_dir()])->expectsOutputToContain('has no starter.json')->assertFailed();

    expect(fn () => (new Starters)->register(blogStarter()->path)->register(blogStarter()->path.'/')->all())->not->toThrow(InvalidArgumentException::class)
        ->and(Collection::query()->count())->toBe(0);
});
