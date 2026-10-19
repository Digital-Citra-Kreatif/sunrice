<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use Illuminate\Contracts\Auth\Access\Authorizable;

/**
 * A settings section on the panel, with the permission to edit it.
 *
 * @internal
 */
final readonly class RegisteredSettings
{
    /** @param class-string<Settings> $class */
    public function __construct(public string $class, public string $permission) {}

    public function key(): string
    {
        return $this->class::key();
    }

    /** The section with its stored values. */
    public function load(): Settings
    {
        return new ($this->class)();
    }

    public function allows(Authorizable $user): bool
    {
        return $user->can($this->permission);
    }
}
