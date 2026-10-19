<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Help\HelpSection;
use Sunrice\Admin\Panel;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('shows the guide to everyone who can open the panel', function () {
    $dir = sys_get_temp_dir().'/help-'.uniqid();
    mkdir($dir);
    file_put_contents("{$dir}/01-orders.md", "# Taking orders\n\nOpen **Orders**. <script>alert(1)</script> [x](javascript:alert(1))");
    usePanel(fn (Panel $panel) => $panel->help(HelpSection::make('shop', 'Shop', $dir, 50)));
    actingAs(adminUser(Panel::ACCESS));

    get('/admin/help')->assertRedirect('/admin/help/panel/getting-started');
    get('/admin/help/shop/orders')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Help/Show')
        ->where('sections.0.key', 'panel')->where('sections.1', ['key' => 'shop', 'title' => 'Shop', 'pages' => [['slug' => 'orders', 'title' => 'Taking orders']]])
        ->where('html', fn (string $html) => str_contains($html, '<strong>Orders</strong>') && ! str_contains($html, '<script') && ! str_contains($html, 'javascript:')));
    get('/admin/help/shop/nope')->assertNotFound();
    get('/admin/help/shop/..%2F..%2Fetc')->assertNotFound();
});
