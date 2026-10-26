<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Panel;
use Sunrice\Cms\Actions\Install\RemoveWelcomeRoute;
use Sunrice\Cms\Actions\Install\ScaffoldCollections;
use Sunrice\Cms\CmsPlugin;
use Sunrice\Cms\Site\AppHomeRoute;

use function Pest\Laravel\artisan;

const STOCK_WEB_ROUTES = <<<'PHP'
    <?php

    use Illuminate\Support\Facades\Route;

    Route::get('/', function () {
        return view('welcome');
    });

    PHP;

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/sunrice-home-route-'.uniqid();
    mkdir("{$this->dir}/vendor/composer", 0755, true);
    file_put_contents("{$this->dir}/vendor/composer/installed.json", json_encode(['packages' => []]));
    $this->routes = "{$this->dir}/web.php";
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->dir);
});

/** The app as `sunrice:install` sees it: the CMS on the panel, routes/web.php in $file. */
function installingWithRoutes(string $dir, string $file): void
{
    Storage::fake('public');
    app()->instance(PackageManifest::class, new PackageManifest(new Filesystem, $dir, "{$dir}/packages.php"));
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    app()->instance(AppHomeRoute::class, new AppHomeRoute(app('router'), $file));
}

it('removes Laravel\'s welcome route from routes/web.php, as it ships and in other spellings', function (string $code, string $expected) {
    $remove = app(RemoveWelcomeRoute::class);

    expect($remove->find($code))->not->toBeNull()
        ->and($remove->edit($code))->toBe($expected)
        ->and($remove->edit($expected))->toBeNull();
})->with([
    'as Laravel ships it' => [STOCK_WEB_ROUTES, "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n"],
    'an arrow function' => ["<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get( \"/\" , fn() => view( 'welcome' ) );\n\nRoute::get('/about', fn () => 'about');\n", "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/about', fn () => 'about');\n"],
    'on one line, among others' => ["<?php\n\nRoute::get('/a', fn () => 'a');\nRoute::get('/', function () { return view('welcome'); });\nRoute::get('/b', fn () => 'b');\n", "<?php\n\nRoute::get('/a', fn () => 'a');\nRoute::get('/b', fn () => 'b');\n"],
    'Route::view' => ["<?php\n\nRoute::view('/', 'welcome');\n", "<?php\n"],
]);

it('leaves any other / route alone', function (string $route) {
    $code = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\n{$route}\n";

    expect(app(RemoveWelcomeRoute::class)->find($code))->toBeNull()
        ->and(app(RemoveWelcomeRoute::class)->edit($code))->toBeNull();
})->with([
    ["Route::get('/', fn () => view('home'));"],
    ["Route::get('/', function () {\n    return view('welcome', ['news' => News::latest()]);\n});"],
    ["Route::get('/welcome', fn () => view('welcome'));"],
    ['Route::get(\'/\', [HomeController::class, \'show\']);'],
]);

it('finds an app route that answers / instead of the CMS\'s homepage', function () {
    $home = app(AppHomeRoute::class);
    expect($home->found())->toBeNull();

    Route::get('/', fn () => 'welcome');
    expect($home->found()?->uri())->toBe('/');

    config(['sunrice-cms.site.enabled' => false]);
    expect($home->found())->toBeNull();
});

it('offers to remove the welcome route on install', function () {
    file_put_contents($this->routes, STOCK_WEB_ROUTES);
    installingWithRoutes($this->dir, $this->routes);
    Route::get('/', fn () => 'welcome');

    artisan('sunrice:install', ['--no-user' => true])
        ->expectsOutputToContain('hides the site\'s homepage')
        ->expectsConfirmation("Remove it from {$this->routes}?", 'yes')
        ->expectsOutputToContain('Welcome route removed')
        ->expectsConfirmation(ScaffoldCollections::QUESTIONS['pages'], 'no')
        ->expectsConfirmation(ScaffoldCollections::QUESTIONS['posts'], 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->routes))->toBe("<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");
});

it('only says what to remove without interaction', function () {
    file_put_contents($this->routes, STOCK_WEB_ROUTES);
    installingWithRoutes($this->dir, $this->routes);
    Route::get('/', fn () => 'welcome');

    artisan('sunrice:install', ['--no-user' => true, '--no-interaction' => true])
        ->expectsOutputToContain("Remove these lines from {$this->routes}")
        ->expectsOutputToContain("Route::get('/', function () {")
        ->assertSuccessful();

    expect(file_get_contents($this->routes))->toBe(STOCK_WEB_ROUTES);
});

it('explains, and changes nothing, when the app\'s / route is its own', function () {
    $code = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n\nRoute::get('/', fn () => view('home'));\n";
    file_put_contents($this->routes, $code);
    installingWithRoutes($this->dir, $this->routes);
    Route::get('/', fn () => 'home');

    artisan('sunrice:install', ['--no-user' => true])
        ->expectsOutputToContain('the app\'s routes win over the CMS\'s')
        ->expectsConfirmation(ScaffoldCollections::QUESTIONS['pages'], 'no')
        ->expectsConfirmation(ScaffoldCollections::QUESTIONS['posts'], 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->routes))->toBe($code);
});

it('says nothing when the CMS answers /', function () {
    file_put_contents($this->routes, STOCK_WEB_ROUTES);
    installingWithRoutes($this->dir, $this->routes);

    artisan('sunrice:install', ['--no-user' => true])
        ->doesntExpectOutputToContain('homepage')
        ->expectsConfirmation(ScaffoldCollections::QUESTIONS['pages'], 'no')
        ->expectsConfirmation(ScaffoldCollections::QUESTIONS['posts'], 'no')
        ->assertSuccessful();

    expect(file_get_contents($this->routes))->toBe(STOCK_WEB_ROUTES);
});
