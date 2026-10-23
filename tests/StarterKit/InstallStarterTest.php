<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Collection;
use Sunrice\Cms\Models\Entry;
use Sunrice\Cms\Models\EntryTranslation;
use Sunrice\Cms\Structure\StructureImportFailed;
use Sunrice\StarterKit\Actions\InstallStarter;
use Sunrice\StarterKit\Starter;
use Sunrice\StarterKit\Starters;

beforeEach(function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    Storage::fake('public');
    $this->starter = sys_get_temp_dir().'/sunrice-starter-'.uniqid();
});

// The starter's files land in the test app: gone after each test.
afterEach(function () {
    $files = new Filesystem;
    foreach ([resource_path('views/cms'), resource_path('views/errors'), public_path('cms'), $this->starter] as $directory) {
        $files->deleteDirectory($directory);
    }
});

/** A starter whose blueprint requires an image, and a published entry pointing at $image. */
function photoStarter(string $path, string $image): Starter
{
    $files = new Filesystem;
    $files->ensureDirectoryExists("{$path}/media");
    $files->copy(app(Starters::class)->find('blog')?->path.'/media/morning.jpg', "{$path}/media/sun.jpg");
    file_put_contents("{$path}/media.json", json_encode(['sun.jpg' => ['title' => 'Sun', 'alt' => 'The sun']]));
    file_put_contents("{$path}/starter.json", json_encode(['name' => 'photos', 'title' => 'Photos', 'locales' => ['en']]));
    file_put_contents("{$path}/site.json", json_encode([
        'sunrice' => 1,
        'locales' => ['en'],
        'blueprints' => [['handle' => 'photo', 'title' => 'Photo', 'fields' => [
            ['handle' => 'image', 'type' => 'media', 'label' => 'Image', 'required' => true, 'config' => ['types' => ['image']]],
        ]]],
        'collections' => [['handle' => 'photos', 'title' => 'Photos', 'blueprint' => 'photo', 'route' => '/photos/{slug}']],
        'entries' => [['collection' => 'photos', 'slug' => 'sunrise', 'translations' => ['en' => [
            'title' => 'Sunrise', 'slug' => 'sunrise', 'data' => ['image' => ['media' => $image]], 'published' => true,
        ]]]],
    ]));

    return Starter::load($path);
}

it('installs a starter whose content requires its own images on a fresh site', function () {
    $dry = app(InstallStarter::class)->handle(photoStarter($this->starter, 'sun.jpg'), dryRun: true);
    expect($dry->uploaded)->toBe(['sun.jpg'])
        ->and(Collection::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0);

    $installation = app(InstallStarter::class)->handle(photoStarter($this->starter, 'sun.jpg'));

    $sun = Media::query()->where('filename', 'sun.jpg')->sole();
    $entry = EntryTranslation::query()->where('draft_slug', 'sunrise')->sole();
    expect($installation->uploaded)->toBe(['sun.jpg'])
        ->and($entry->data['image'])->toBe($sun->id)
        ->and($entry->isLive())->toBeTrue();
});

it('refuses a starter whose content requires an image it doesn\'t bring, before saving or uploading anything', function () {
    expect(fn () => app(InstallStarter::class)->handle(photoStarter($this->starter, 'missing.jpg')))
        ->toThrow(StructureImportFailed::class);

    expect(Collection::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('saves nothing and deletes the files it uploaded when the install fails after its check', function () {
    // Something going wrong once its files are uploaded (the check, with stand-ins, has passed).
    Event::listen('eloquent.created: '.Entry::class, function (): void {
        if (Storage::disk('public')->allFiles() !== []) {
            throw new RuntimeException('Gone wrong.');
        }
    });

    expect(fn () => app(InstallStarter::class)->handle(photoStarter($this->starter, 'sun.jpg')))->toThrow(RuntimeException::class, 'Gone wrong.');
    expect(Media::query()->count())->toBe(0)
        ->and(Collection::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and(is_dir(resource_path('views/cms')))->toBeFalse();
});

it('installs on a SQLite file with Laravel\'s defaults while a queue worker takes jobs from it', function () {
    // A fresh app's database: a file, no busy timeout or journal mode set, the database queue, and a worker on its own connection.
    $file = $this->starter.'.sqlite';
    $default = config('database.default');
    touch($file);
    $sqlite = ['driver' => 'sqlite', 'database' => $file, 'prefix' => '', 'foreign_key_constraints' => true, 'busy_timeout' => null, 'journal_mode' => null, 'synchronous' => null];
    $queue = ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90];
    config([
        'database.connections.site' => $sqlite,
        'database.connections.worker' => $sqlite,
        'database.default' => 'site',
        'queue.default' => 'database',
        'queue.connections.database' => $queue + ['connection' => null],
        'queue.connections.worker' => $queue + ['connection' => 'worker'],
        'queue.failed.database' => 'worker',
    ]);
    $this->artisan('migrate', ['--database' => 'site'])->assertSuccessful();

    // The worker polls while the entries are saved, as `queue:work` does, and puts back what it takes.
    $errors = [];
    Event::listen('eloquent.created: '.Entry::class, function () use (&$errors): void {
        try {
            Queue::connection('worker')->pop()?->release();
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    });
    try {
        app(InstallStarter::class)->handle(photoStarter($this->starter, 'sun.jpg'));

        expect($errors)->toBe([])
            ->and(DB::connection('worker')->table('failed_jobs')->count())->toBe(0)
            ->and(Queue::connection('worker')->size())->toBe(1)
            ->and(EntryTranslation::query()->where('draft_slug', 'sunrise')->sole()->isLive())->toBeTrue();
    } finally {
        config(['database.default' => $default]); // the test's own, which RefreshDatabase rolls back
        DB::purge('site');
        DB::purge('worker');
        @unlink($file);
    }
});
