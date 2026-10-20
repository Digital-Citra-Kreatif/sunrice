<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Something users own, registered with `$panel->userContent()`: how to
 * count a user's, and what to do with it when the user is deleted
 * (see Sunrice\Admin\Actions\Users\DeleteUser).
 *
 * @internal
 */
final class UserContent
{
    /**
     * @param  string  $label  plural, lowercase: "entries"
     * @param  Closure(Model): int  $count
     * @param  Closure(Model, string, ?Model): void  $handle  the user, the mode (reassign, keep or delete) and, when reassigning, the heir
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly Closure $count,
        public readonly Closure $handle,
    ) {}
}
