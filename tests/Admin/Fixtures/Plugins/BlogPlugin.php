<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures\Plugins;

use Sunrice\Admin\Contracts\Plugin;
use Sunrice\Admin\Panel;

/** A plugin a package offers in its composer.json, for the install tests. */
final class BlogPlugin implements Plugin
{
    public static function make(): self
    {
        return new self;
    }

    public function id(): string
    {
        return 'blog';
    }

    public function requires(): array
    {
        return [];
    }

    public function register(Panel $panel): void {}

    public function boot(Panel $panel): void {}
}
