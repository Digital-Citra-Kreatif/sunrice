<?php

declare(strict_types=1);

namespace Sunrice\StarterKit;

use Illuminate\Support\ServiceProvider;

/**
 * The starter kit: the Starters registry (with the kit's own example
 * starters) and its commands. Its guide shows on Help → Docs with
 * StarterKitPlugin added to the panel.
 *
 * @api
 */
final class StarterKitServiceProvider extends ServiceProvider
{
    /** The kit's own starters, as examples to install or to copy from. */
    public const STARTERS = ['blog'];

    public function register(): void
    {
        $this->app->singleton(Starters::class, function (): Starters {
            $starters = new Starters;
            foreach (self::STARTERS as $name) {
                $starters->register(dirname(__DIR__)."/starters/{$name}");
            }

            return $starters;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\StartersCommand::class,
                Console\InstallStarterCommand::class,
                Console\MakeStarterCommand::class,
            ]);
        }
    }
}
