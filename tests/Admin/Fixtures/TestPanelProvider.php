<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Closure;
use Sunrice\Admin\Panel;
use Sunrice\Admin\PanelProvider;

/** An app panel provider whose panel each test configures (see usePanel()). */
final class TestPanelProvider extends PanelProvider
{
    /** @var (Closure(Panel): Panel)|null */
    public static ?Closure $configure = null;

    public function panel(Panel $panel): Panel
    {
        return self::$configure === null ? $panel : (self::$configure)($panel);
    }
}
