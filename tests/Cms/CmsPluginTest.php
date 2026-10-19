<?php

declare(strict_types=1);

use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;

it('is a panel plugin', function () {
    $panel = Panel::make()->plugin(CmsPlugin::make());
    $panel->boot();

    expect($panel->getPlugin('cms'))->toBeInstanceOf(CmsPlugin::class);
});
