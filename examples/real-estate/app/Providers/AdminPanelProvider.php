<?php

declare(strict_types=1);

namespace App\Providers;

use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->path('admin')
            ->brand('Lumina Estates')
            ->plugin(CmsPlugin::make());
    }
}
