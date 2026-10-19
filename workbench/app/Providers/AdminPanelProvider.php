<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;
use Sunrice\Cms\CmsPlugin;
use Workbench\App\Admin\ProductResource;

/** The workbench app's panel, set up the way an app would. */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->path('admin')
            ->brand('Sunrice')
            ->plugin(CmsPlugin::make())
            ->resources(ProductResource::class);
    }
}
