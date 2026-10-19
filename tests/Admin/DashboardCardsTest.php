<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Access\Authorizable;
use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Activity\ActivityLog;
use Sunrice\Admin\Dashboard\DashboardCard;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows the cards the user may see, their content after the page', function () {
    usePanel(fn (Panel $panel) => $panel->dashboardCards(
        DashboardCard::make('shop.sales')->title('Sales')->sort(5)->span(2)->everyone()
            ->stats(fn (Authorizable $user) => [['label' => 'Orders', 'value' => 12, 'url' => '/admin/shop/orders'], ['label' => 'Bad', 'value' => 1, 'url' => 'javascript:alert(1)']]),
        DashboardCard::make('shop.secret')->title('Secret')->permission('shop.secret')->list(fn () => [['title' => 'Hidden']]),
        DashboardCard::make('shop.mine')->title('Mine')->visible(fn (Authorizable $user) => true)->list(fn () => [])->empty('None of yours.')->link('All', 'shop/mine'),
    ));
    config(['inertia.testing.ensure_pages_exist' => false]);
    actingAs(adminUser(Panel::ACCESS, 'sunrice.activity.view'));
    app(ActivityLog::class)->record('created', 'product', null, 'Mug');

    get('/admin')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('cards.0.key', 'shop.sales')->where('cards.0.span', 2)->where('cards.0.type', 'stats')
        ->where('cards.1.key', 'shop.mine')->where('cards.1.link', ['label' => 'All', 'url' => '/admin/shop/mine'])
        ->where('cards.2.key', 'activity')
        ->has('cards', 3)
        ->missing('content')
        ->loadDeferredProps(fn (Assert $reload) => $reload->where('content', function ($content): bool {
            $content = collect($content)->toArray();
            expect($content['shop.sales']['items'])->toBe([['label' => 'Orders', 'value' => '12', 'url' => '/admin/shop/orders'], ['label' => 'Bad', 'value' => '1', 'url' => null]])
                ->and($content['shop.mine'])->toBe(['items' => [], 'empty' => 'None of yours.'])
                ->and($content['activity']['items'][0]['title'])->toBe('Rina created Mug')
                ->and($content)->not->toHaveKey('shop.secret');

            return true;
        })));
});

it('refuses cards nobody guards, or two with one key', function () {
    expect(fn () => Panel::make()->dashboardCards(DashboardCard::make('open')->list(fn () => [])))->toThrow(PanelMisconfigured::class, 'says nobody may see it')
        ->and(fn () => Panel::make()->dashboardCards(DashboardCard::make('a')->everyone(), DashboardCard::make('a')->everyone()))->toThrow(PanelMisconfigured::class, 'Two dashboard cards');
});
