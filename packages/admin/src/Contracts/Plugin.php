<?php

declare(strict_types=1);

namespace Sunrice\Admin\Contracts;

use Sunrice\Admin\Panel;

/**
 * Something that adds to the admin panel: the CMS, commerce, your own
 * module. Added with `$panel->plugin(...)` in the app's panel provider.
 *
 * The panel calls register() on every plugin first (dependencies before
 * the plugins that require them), checks what they declared, then calls
 * boot() in the same order.
 *
 * @api
 */
interface Plugin
{
    /** A unique, lowercase id: "cms", "commerce". Also the prefix of its permissions. */
    public function id(): string;

    /**
     * Ids of the plugins this one needs. The panel refuses to boot when
     * one is missing.
     *
     * @return list<string>
     */
    public function requires(): array;

    /** Declare navigation, permissions, shared data… on the panel. */
    public function register(Panel $panel): void;

    /** Runtime wiring, after every plugin has registered. */
    public function boot(Panel $panel): void;
}
