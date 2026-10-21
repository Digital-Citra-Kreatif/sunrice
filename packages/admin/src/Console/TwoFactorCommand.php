<?php

declare(strict_types=1);

namespace Sunrice\Admin\Console;

use Illuminate\Console\Command;
use Sunrice\Admin\Actions\Settings\SaveSettings;
use Sunrice\Admin\Auth\TwoFactorLogin;
use Sunrice\Admin\Security\SecuritySettings;
use Sunrice\Admin\Settings\MailSettings;

/**
 * sunrice:two-factor {on|off} — two-factor login from the console, for
 * when nobody can log in (the app stopped sending email).
 *
 * @internal
 */
final class TwoFactorCommand extends Command
{
    protected $signature = 'sunrice:two-factor {state? : on or off; leave out to see it}';

    protected $description = 'Turn two-factor (emailed code) login on or off';

    public function handle(SaveSettings $save): int
    {
        $state = $this->argument('state');
        if ($state === null) {
            $this->components->info('Two-factor login is '.(TwoFactorLogin::enabled() ? 'on' : 'off').'.');

            return self::SUCCESS;
        }
        if (! in_array($state, ['on', 'off'], true)) {
            $this->components->error('Use "on" or "off".');

            return self::FAILURE;
        }

        $save->handle(app(SecuritySettings::class), ['twoFactor' => $state === 'on']);
        if ($state === 'on' && ! MailSettings::info()['delivers']) {
            $this->components->warn('The '.MailSettings::info()['transport'].' mailer doesn\'t deliver email: nobody will get a login code until mail is set up.');
        }
        if (is_bool(config('sunrice-admin.auth.two_factor'))) {
            $this->components->warn('The sunrice-admin.auth.two_factor config overrides the setting: it\'s '.(TwoFactorLogin::enabled() ? 'on' : 'off').'.');
        }
        $this->components->info("Two-factor login is set {$state}.");

        return self::SUCCESS;
    }
}
