<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SeedRoles;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Http\Controllers\HelpController;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Permissions\Permission;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * A panel with a "shop" plugin that registers a guide and docs of its own.
 *
 * @param  (Closure(Panel): void)|null  $more
 */
function helpPanel(?Closure $more = null): Panel
{
    return usePanel(fn (Panel $panel) => $panel->brand('Acme')->plugin(new TestPlugin('shop', [], function (Panel $panel) use ($more): void {
        $panel->guide('Shop', __DIR__.'/Fixtures/help/guide');
        $panel->docs('Shop', __DIR__.'/Fixtures/help/docs');
        if ($more !== null) {
            $more($panel);
        }
    })));
}

it('registers the panel\'s own docs and guide, and the Help permissions', function () {
    helpPanel();
    $panel = app(Panel::class);

    expect(array_keys($panel->getDocs()))->toBe(['panel', 'shop'])
        ->and(array_keys($panel->getGuides()))->toBe(['panel', 'shop'])
        ->and($panel->getGuides()['panel']['directory'])->toBe(dirname(__DIR__, 2).'/packages/admin/guide')
        ->and($panel->getPermissions())->toHaveKeys(['sunrice.docs.view', 'sunrice.guide.view'])
        ->and($panel->isPrivilegedPermission('sunrice.docs.view'))->toBeFalse();
});

it('refuses a guide without a usable title or registered twice', function () {
    $panel = Panel::make()->plugin(new TestPlugin('shop', [], fn (Panel $p) => $p->guide('!!', '/tmp')));
    expect(fn () => $panel->boot())->toThrow(PanelMisconfigured::class, 'needs a title');

    $panel = Panel::make()->plugin(new TestPlugin('shop', [], fn (Panel $p) => $p->guide('Panel', '/tmp')));
    expect(fn () => $panel->boot())->toThrow(PanelMisconfigured::class, 'Two guides are called "Panel" (by the plugin "shop")');
});

it('gives every default role that opens the panel the guide', function () {
    helpPanel(function (Panel $p): void {
        $p->permissions(Permission::make('shop.orders.view', 'View orders', 'Shop'));
        $p->defaultRole('clerk', [Panel::ACCESS, 'shop.orders.view']);
        $p->defaultRole('robot', ['shop.orders.view']);
    });
    $roles = app(Panel::class)->getDefaultRoles();

    expect($roles['clerk'])->toBe([Panel::ACCESS, 'shop.orders.view', 'sunrice.guide.view'])
        ->and($roles['robot'])->toBe(['shop.orders.view'])
        ->and($roles['administrator'])->toBe(['*']);

    app(SeedRoles::class)->handle();
    expect(Role::findByName('clerk', 'web')->hasPermissionTo('sunrice.guide.view'))->toBeTrue();
});

it('shows the Help group in the sidebar by permission', function () {
    helpPanel();
    $help = fn (array $navigation) => collect($navigation)->firstWhere('group', 'Help')['items'] ?? [];

    $reader = adminUser(Panel::ACCESS, 'sunrice.guide.view');
    expect(collect($help(app(Panel::class)->navigationFor($reader)))->pluck('url')->all())->toBe(['guide']);

    $developer = adminUser(Panel::ACCESS, 'sunrice.guide.view', 'sunrice.docs.view');
    expect(collect($help(app(Panel::class)->navigationFor($developer)))->pluck('label')->all())->toBe(['Guide', 'Developer docs']);

    expect($help(app(Panel::class)->navigationFor(adminUser(Panel::ACCESS))))->toBe([]);
});

it('guards the Help pages with their permissions', function () {
    helpPanel();
    actingAs(adminUser(Panel::ACCESS));
    get('/admin/guide')->assertForbidden();
    get('/admin/docs')->assertForbidden();
    get('/admin/guide/images/shop/orders.png')->assertForbidden();

    auth()->logout();
    get('/admin/guide')->assertRedirect('/admin/login');
});

it('renders a guide page as sanitised HTML with its headings and the panel\'s name', function () {
    helpPanel();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.guide.view'));

    $response = get('/admin/guide/shop/orders')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Help/Guide', false)
        ->where('page', 'shop/orders')
        ->where('title', 'Orders in Acme')
        ->where('headings', [
            ['id' => 'finding-an-order', 'text' => 'Finding an order', 'level' => 2],
            ['id' => 'finding-an-order-2', 'text' => 'Finding an order', 'level' => 3],
        ])
        ->where('language', 'en')
        ->where('languages', [['code' => 'en', 'label' => 'English'], ['code' => 'id', 'label' => 'Bahasa Indonesia']])
        ->where('shownIn', null)
        ->where('previous', fn ($previous) => $previous['page'] === 'panel/activity-log')
        ->where('next', ['page' => 'shop/refunds', 'title' => 'Refunds'])
        ->where('sections', fn ($sections) => collect($sections)->pluck('key')->all() === ['panel', 'shop']
            && collect($sections)->last()['pages'] === [['page' => 'shop/orders', 'title' => 'Orders in Acme'], ['page' => 'shop/refunds', 'title' => 'Refunds']])
        ->where('html', function (string $html): bool {
            expect($html)
                ->not->toContain('<h1')
                ->toContain('<h2 id="finding-an-order">Finding an order</h2>')
                ->toContain('<h3 id="finding-an-order-2">')
                // Links between pages, to the developer docs only for those who may read them.
                ->toContain('<a href="/admin/guide/shop/refunds#giving-money-back">refunds</a>')
                ->toContain('the developer notes')
                ->not->toContain('docs:shop')
                ->not->toContain('/admin/docs/')
                // Images from the guide's folder; others are left out.
                ->toContain('<img src="/admin/guide/images/shop/orders.png" alt="The orders list" loading="lazy" />')
                ->not->toContain('evil.example')
                // Nothing that runs.
                ->not->toContain('<script')
                ->not->toMatch('/<img[^>]*onerror/')
                ->toContain('&lt;img src')
                ->not->toContain('javascript:')
                ->toContain('<aside class="sunrice-callout sunrice-callout-tip" role="note"><p class="sunrice-callout-title">Tip</p><p>Use the filters.</p></aside>');

            return true;
        }));
    expect($response->getContent())->not->toContain('<script>alert');
});

it('uses the branded name and links the docs for developers', function () {
    helpPanel();
    app(BrandingSettings::class)->fill(['name' => 'Toko <Rina>'])->save();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.guide.view', 'sunrice.docs.view'));

    get('/admin/guide/shop/orders')->assertInertia(fn (Assert $page) => $page
        ->where('title', 'Orders in Toko <Rina>')
        ->where('html', fn (string $html) => str_contains($html, '<a href="/admin/docs/shop/api">developer notes</a>')));
});

it('switches the guide\'s language and remembers it', function () {
    helpPanel();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.guide.view'));

    get('/admin/guide/shop/orders?lang=id')->assertInertia(fn (Assert $page) => $page
        ->where('language', 'id')
        ->where('title', 'Pesanan di Acme')
        ->where('html', fn (string $html) => str_contains($html, '<p class="sunrice-callout-title">Tips</p>')));
    expect(session(HelpController::SESSION_KEY))->toBe('id');

    // A page not written in Indonesian is shown in English, and says so.
    get('/admin/guide/shop/refunds')->assertInertia(fn (Assert $page) => $page
        ->where('language', 'id')
        ->where('title', 'Refunds')
        ->where('shownIn', 'en'));

    // An unknown language is ignored.
    get('/admin/guide/shop/orders?lang=xx')->assertInertia(fn (Assert $page) => $page->where('language', 'id'));
});

it('opens the first page and answers 404 for unknown pages', function () {
    helpPanel();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.guide.view', 'sunrice.docs.view'));

    get('/admin/guide')->assertInertia(fn (Assert $page) => $page->where('page', 'panel/welcome'));
    get('/admin/docs')->assertInertia(fn (Assert $page) => $page->component('Help/Docs', false)->where('page', fn (string $first) => str_starts_with($first, 'panel/')));
    get('/admin/guide/shop/missing')->assertNotFound();
    get('/admin/guide/nope/orders')->assertNotFound();
    get('/admin/docs/shop/..%2F..%2Fguide%2Fen%2F01-orders')->assertNotFound();
    get('/admin/docs/shop/api.md')->assertNotFound();
});

it('renders the developer docs with links between sets', function () {
    helpPanel();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.docs.view'));

    get('/admin/docs/shop/api')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Help/Docs', false)
        ->where('title', 'The shop API')
        ->where('headings', [])
        ->where('html', function (string $html): bool {
            expect($html)
                // Relative links to another registered set's file open that page.
                ->toContain('<a href="/admin/docs/panel/resources#columns">panel&#039;s resources</a>')
                // The guide is linked only for those who may read it.
                ->toContain('the guide')
                ->not->toContain('/admin/guide/')
                ->toContain('## Not a heading');

            return true;
        }));
});

it('serves guide images from the images folder only', function () {
    helpPanel();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.guide.view'));

    $response = get('/admin/guide/images/shop/orders.png')->assertOk();
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    get('/admin/guide/images/shop/secret.png')->assertNotFound();
    get('/admin/guide/images/shop/..%2Fsecret.png')->assertNotFound();
    get('/admin/guide/images/shop/orders.php')->assertNotFound();
    get('/admin/guide/images/nope/orders.png')->assertNotFound();
});
it('ships the panel\'s own guide in English and Indonesian', function () {
    helpPanel();
    actingAs(adminUser(Panel::ACCESS, 'sunrice.guide.view'));

    $english = glob(dirname(__DIR__, 2).'/packages/admin/guide/en/*.md') ?: [];
    $indonesian = glob(dirname(__DIR__, 2).'/packages/admin/guide/id/*.md') ?: [];
    expect($english)->not->toBeEmpty()
        ->and(array_map(basename(...), $indonesian))->toBe(array_map(basename(...), $english));

    foreach ($english as $file) {
        $page = 'panel/'.preg_replace('/^\d+-/', '', basename($file, '.md'));
        get("/admin/guide/{$page}?lang=en")->assertOk();
        get("/admin/guide/{$page}?lang=id")->assertOk()->assertInertia(fn (Assert $p) => $p->where('shownIn', null));
    }
});
