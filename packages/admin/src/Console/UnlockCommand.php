<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Security\AccountGuard;

/**
 * sunrice:unlock {email} — unlock an account an administrator locked (or
 * that's locked after too many wrong passwords), from the console.
 *
 * @internal
 */
final class UnlockCommand extends Command
{
    protected $signature = 'sunrice:unlock {email : The account\'s email}';

    protected $description = 'Unlock an admin panel account';

    public function handle(Panel $panel, AccountGuard $accounts): int
    {
        $email = $this->argument('email');
        $user = $panel->getUserModel()::query()->where('email', $email)->first();
        if ($user === null) {
            $this->components->error('No account has that email.');

            return self::FAILURE;
        }
        $accounts->unlock($user);
        $this->components->info("{$email} can log in again.");

        return self::SUCCESS;
    }
}
