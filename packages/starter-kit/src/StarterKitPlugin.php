<?php

declare(strict_types=1);

namespace Sunrice\StarterKit;

use Sunrice\Admin\BasePlugin;
use Sunrice\Admin\Panel;
use Sunrice\Cms\CmsPlugin;

/**
 * Optional: adds the starter kit's guide to Help → Docs (and to the AI
 * agents' read_docs). The commands work without it.
 *
 *     $panel->plugin(StarterKitPlugin::make());
 *
 * @api
 */
final class StarterKitPlugin extends BasePlugin
{
    public function id(): string
    {
        return 'starter-kit';
    }

    public function requires(): array
    {
        return [CmsPlugin::ID];
    }

    public function register(Panel $panel): void
    {
        $panel->docs('Starter kit', dirname(__DIR__).'/docs');
    }
}
