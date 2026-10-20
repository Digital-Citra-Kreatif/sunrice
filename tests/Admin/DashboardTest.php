<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Dashboard\Widget;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Panel;
use Sunrice\Tests\Admin\Fixtures\TestPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

// Reloads (deferred props) look for the page file; the pages are the panel's frontend.
beforeEach(fn () => config(['inertia.testing.ensure_pages_exist' => false]));

/** A stat widget with fixed data. */
function statWidget(string $key, int $value = 1): Widget
{
    return Widget::stat($key)->title(ucfirst($key))->data(fn (): array => ['value' => $value]);
}

it('registers widgets with the same ownership rules as permissions', function () {
    $panel = Panel::make()->widgets(statWidget('orders'))
        ->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->widgets(statWidget('shop.revenue'))));
    $panel->boot();
    expect(array_keys($panel->getWidgets()))->toContain('orders', 'shop.revenue', 'sunrice.activity', 'sunrice.media')
        ->and($panel->getWidgets()['shop.revenue']->getGroup())->toBe('widgets-shop')
        ->and($panel->getWidgets()['orders']->getGroup())->toBe('widgets');

    expect(fn () => Panel::make()->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->widgets(statWidget('cms.entries'))))->boot())
        ->toThrow(PanelMisconfigured::class, 'a plugin\'s widget keys start with its id ("shop.…")');
    expect(fn () => Panel::make()->plugin(new TestPlugin('shop'))->widgets(statWidget('shop.secret')))
        ->toThrow(PanelMisconfigured::class, 'uses the prefix of the plugin "shop"');
    expect(fn () => Panel::make()->widgets(statWidget('a'), statWidget('a')))
        ->toThrow(PanelMisconfigured::class, 'Two widgets have the key "a"');
    expect(fn () => Panel::make()->widgets(statWidget('sunrice.media'))->boot())
        ->toThrow(PanelMisconfigured::class, 'Two widgets have the key "sunrice.media"');
    expect(fn () => Panel::make()->widgets(Widget::list('nodata')))
        ->toThrow(PanelMisconfigured::class, 'has no data');
    expect(fn () => Widget::stat('Bad Key'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Widget::stat('ok')->span(4))->toThrow(InvalidArgumentException::class);

    $booted = Panel::make();
    $booted->boot();
    expect(fn () => $booted->widgets(statWidget('late')))->toThrow(PanelMisconfigured::class);
});

it('gives a user the widgets they may see, in order', function () {
    $panel = Panel::make()->mediaLibrary(false)->widgets(
        statWidget('second')->sort(20),
        statWidget('first')->sort(10),
        statWidget('also-second')->sort(20),
        statWidget('guarded')->permission('shop.orders.view'),
        statWidget('hidden')->visible(fn (Authorizable $user): bool => false),
    );
    $panel->boot();

    $keys = fn (string ...$permissions): array => array_map(fn (Widget $w) => $w->key, $panel->widgetsFor(new class($permissions) implements Authorizable
    {
        /** @param list<string> $permissions */
        public function __construct(private array $permissions) {}

        public function can($abilities, $arguments = []): bool
        {
            return in_array($abilities, $this->permissions, true);
        }
    }));

    expect($keys())->toBe(['first', 'second', 'also-second'])
        ->and($keys('shop.orders.view', 'sunrice.activity.view'))->toBe(['first', 'second', 'also-second', 'guarded', 'sunrice.activity']);
});

it('checks the shape of widget data and drops unsafe links', function () {
    $user = adminUser();
    $list = Widget::list('l')->data(fn (): array => ['count' => 3, 'rows' => [
        ['title' => 'A', 'url' => 'shop/orders/1', 'tone' => 'success', 'badge' => 'Paid'],
        ['title' => 'B', 'url' => 'javascript:alert(1)', 'tone' => 'loud'],
        ['title' => 'C', 'url' => '//evil.example'],
        ['title' => 'D', 'url' => '/\\evil.example'],
        ['title' => 'E', 'url' => 'https://example.com/x'],
        'not a row',
    ]]);

    $data = $list->resolve($user);
    expect($data['count'])->toBe(3)
        ->and(array_column($data['rows'], 'url'))->toBe(['shop/orders/1', null, null, null, 'https://example.com/x'])
        ->and(array_column($data['rows'], 'tone'))->toBe(['success', 'neutral', 'neutral', 'neutral', 'neutral'])
        ->and($data['rows'][0]['badge'])->toBe('Paid');

    expect(Widget::stat('s')->data(fn (): array => ['value' => 12, 'trend' => 'sideways', 'delta' => '+2'])->resolve($user))
        ->toBe(['value' => 12, 'delta' => '+2', 'trend' => null, 'description' => null]);
    expect(Widget::cards('c')->data(fn (): array => ['cards' => [['title' => 'Docs', 'url' => 'docs', 'text' => 'Read']]])->resolve($user)['cards'][0])
        ->toBe(['title' => 'Docs', 'text' => 'Read', 'url' => 'docs', 'icon' => null]);

    expect(fn () => Widget::stat('s')->data(fn (): array => [])->resolve($user))->toThrow(InvalidArgumentException::class, 'needs a "value"')
        ->and(fn () => Widget::list('l')->data(fn (): array => ['rows' => [['meta' => 'x']]])->resolve($user))->toThrow(InvalidArgumentException::class, 'needs a "title"');
});

it('sends the definitions with the page and the data deferred, per plugin', function () {
    $ran = (object) ['times' => 0];
    usePanel(fn (Panel $panel) => $panel->widgets(
        Widget::stat('orders')->title('Orders')->link('shop/orders')->span(2)->data(function () use ($ran): array {
            $ran->times++;

            return ['value' => 42, 'delta' => '+3 today'];
        }),
    )->plugin(new TestPlugin('shop', register: fn (Panel $p) => $p->widgets(
        Widget::list('shop.latest')->title('Latest')->empty('None yet.')->data(fn (): array => ['rows' => [['title' => 'Order 1']]]),
    ))));
    actingAs(adminUser(Panel::ACCESS));

    get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard', shouldExist: false)
        ->where('widgets.0.key', 'orders')
        ->where('widgets.0.span', 2)
        ->where('widgets.0.link', ['url' => 'shop/orders', 'label' => null])
        ->where('widgets.0.prop', 'widget_orders')
        ->where('widgets.1.prop', 'widget_shop_latest')
        ->where('widgets.1.empty', 'None yet.')
        ->missing('widget_orders')
        ->missing('widget_shop_latest')
        ->loadDeferredProps('widgets', fn (Assert $reload) => $reload
            ->where('widget_orders', ['ok' => true, 'data' => ['value' => 42, 'delta' => '+3 today', 'trend' => null, 'description' => null]])
            ->missing('widget_shop_latest'))
        ->loadDeferredProps('widgets-shop', fn (Assert $reload) => $reload
            ->where('widget_shop_latest.data.rows.0.title', 'Order 1')));

    // Only the deferred request ran the data.
    expect($ran->times)->toBe(1);
});

it('shows a widget whose data fails as unavailable, and reports it', function () {
    Exceptions::fake();
    usePanel(fn (Panel $panel) => $panel->widgets(
        Widget::stat('broken')->data(fn (): array => throw new RuntimeException('Database down')),
        statWidget('fine', 7),
    ));
    actingAs(adminUser(Panel::ACCESS));

    get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps('widgets', fn (Assert $reload) => $reload
            ->where('widget_broken', ['ok' => false])
            ->where('widget_fine.data.value', 7)));

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Database down');
});

it('has the panel\'s own widgets: recent activity and media', function () {
    usePanel();
    $user = adminUser(Panel::ACCESS, 'sunrice.activity.view', 'sunrice.media.view');
    actingAs($user);
    app(ActivityLog::class)->record('updated', 'settings', 'general', 'General');
    Media::query()->create(['disk' => 'public', 'path' => 'a.jpg', 'filename' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 10, 'sizes' => []]);

    get('/admin')->assertInertia(fn (Assert $page) => $page
        ->where('widgets.0.key', 'sunrice.media')
        ->where('widgets.1.key', 'sunrice.activity')
        ->loadDeferredProps('widgets', fn (Assert $reload) => $reload
            ->where('widget_sunrice_media.data.value', 1)
            ->where('widget_sunrice_media.data.delta', '+1 this week')
            ->where('widget_sunrice_activity.data.rows.0.title', 'Rina updated General')
            ->where('widget_sunrice_activity.data.rows.0.url', 'activity?type=settings&subject=general')));

    // Without the permissions, neither.
    actingAs(adminUser(Panel::ACCESS));
    get('/admin')->assertInertia(fn (Assert $page) => $page->where('widgets', []));
});
