<?php

declare(strict_types=1);

use Sunrice\Admin\AdminServiceProvider;
use Sunrice\Cms\CmsServiceProvider;

it('boots the panel without the CMS', function () {
    expect(app()->getProviders(AdminServiceProvider::class))->toHaveCount(1)
        ->and(app()->getProviders(CmsServiceProvider::class))->toBeEmpty();
});
