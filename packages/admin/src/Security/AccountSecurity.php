<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An account's security state: locked by an administrator, signed out
 * everywhere at some time, or asked to choose a stronger password. Kept apart from the app's user table.
 *
 * @property int $id
 * @property string $user_id
 * @property Carbon|null $locked_at
 * @property string|null $locked_by
 * @property string|null $locked_reason
 * @property Carbon|null $signed_out_at
 * @property string|null $weak_password a fingerprint of the weak password signed in with (WeakPasswordAtLogin)
 *
 * @internal
 */
final class AccountSecurity extends Model
{
    protected $table = 'sunrice_account_security';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['locked_at' => 'datetime', 'signed_out_at' => 'datetime'];
    }
}
