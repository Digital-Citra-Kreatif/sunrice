<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('has the CMS\'s guide for editors', function () {
    usePanel(fn (Panel $panel) => $panel->plugin(CmsPlugin::make()));
    actingAs(adminUser(Panel::ACCESS));

    get('/admin/help/content/publishing')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sections.1.key', 'content')->where('sections.1.pages.0.title', 'Writing entries'));
});
