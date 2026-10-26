<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Blueprints\SaveBlueprint;
use Sunrice\Cms\Actions\Collections\DeleteCollection;
use Sunrice\Cms\Actions\Collections\SaveCollection;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Models\Blueprint;
use Sunrice\Cms\Models\Collection;

use function Pest\Laravel\artisan;

const PAGES_QUESTION = 'Create a Pages collection (nested pages at /{slug})?';
const POSTS_QUESTION = 'Create a Posts collection (dated posts at /blog/{slug}, listed at /blog)?';

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/sunrice-scaffold-'.uniqid();
    mkdir("{$this->dir}/vendor/composer", 0755, true);
    file_put_contents("{$this->dir}/vendor/composer/installed.json", json_encode(['packages' => []]));
    Storage::fake('public');
    app()->instance(PackageManifest::class, new PackageManifest(new Filesystem, $this->dir, "{$this->dir}/packages.php"));
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->dir);
});

it('offers Pages and Posts on install and makes those chosen', function () {
    artisan('sunrice:install', ['--no-user' => true])
        ->expectsConfirmation(PAGES_QUESTION, 'yes')
        ->expectsConfirmation(POSTS_QUESTION, 'no')
        ->assertSuccessful();

    $pages = Collection::query()->sole();
    expect($pages->handle)->toBe('pages')
        ->and($pages->blueprint)->toBe('page')
        ->and($pages->hierarchical)->toBeTrue()
        ->and($pages->route)->toBe('/{slug}')
        ->and($pages->comments)->toBeFalse()
        ->and(Blueprint::query()->pluck('handle')->all())->toBe(['page'])
        // The collection's own permissions, as when it's made in the admin.
        ->and(Permission::query()->where('name', 'cms.entries.pages.view')->exists())->toBeTrue();

    // Asked again only about what's missing.
    artisan('sunrice:install', ['--no-user' => true])
        ->expectsConfirmation(POSTS_QUESTION, 'yes')
        ->assertSuccessful();

    $posts = Collection::query()->where('handle', 'posts')->sole();
    expect($posts->blueprint)->toBe('post')
        ->and($posts->dated)->toBeTrue()
        ->and($posts->route)->toBe('/blog/{slug}')
        ->and($posts->listing_route)->toBe('/blog')
        ->and($posts->comments)->toBeTrue();

    artisan('sunrice:install', ['--no-user' => true])
        ->doesntExpectOutputToContain('Create a P')
        ->assertSuccessful();
});

it('makes nothing on install without interaction', function () {
    artisan('sunrice:install', ['--no-user' => true, '--no-interaction' => true])->assertSuccessful();

    expect(Collection::query()->count())->toBe(0)
        ->and(Blueprint::query()->count())->toBe(0);
});

it('makes both, or those named, with the command, and is safe to run again', function () {
    artisan('sunrice:scaffold-collections', ['--posts' => true])->assertSuccessful();
    expect(Collection::query()->pluck('handle')->all())->toBe(['posts']);

    artisan('sunrice:scaffold-collections')->expectsOutputToContain('Pages')->assertSuccessful();
    expect(Collection::query()->orderBy('handle')->pluck('handle')->all())->toBe(['pages', 'posts']);

    artisan('sunrice:scaffold-collections')->expectsOutputToContain('has these collections already')->assertSuccessful();
    expect(Collection::query()->count())->toBe(2);
});

it('leaves the site\'s own collections and blueprints alone, in the trash too', function () {
    app(SaveBlueprint::class)->handle(['handle' => 'post', 'title' => 'My post', 'fields' => [['handle' => 'summary', 'type' => 'textarea']]]);
    $pages = app(SaveCollection::class)->handle(['handle' => 'pages', 'title' => 'My pages']);
    app(DeleteCollection::class)->handle($pages);

    artisan('sunrice:scaffold-collections')->assertSuccessful();

    expect(Collection::withTrashed()->where('handle', 'pages')->sole()->title)->toBe('My pages')
        ->and(Collection::query()->where('handle', 'posts')->sole()->blueprint)->toBe('post')
        ->and(Blueprint::query()->where('handle', 'post')->sole()->title)->toBe('My post')
        ->and(Blueprint::query()->where('handle', 'page')->exists())->toBeFalse();
});

it('says why when a collection can\'t be made, and makes none', function () {
    app(SaveCollection::class)->handle(['handle' => 'articles', 'title' => 'Articles'])->forceFill(['listing_route' => '/blog'])->save();

    artisan('sunrice:scaffold-collections')->expectsOutputToContain('listing')->assertFailed();

    expect(Collection::query()->pluck('handle')->all())->toBe(['articles']);
});
