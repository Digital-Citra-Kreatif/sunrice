<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Toggle;
use Sunrice\Admin\Settings\Settings;

/**
 * Settings → Security, guarded by sunrice.settings.security. Part of
 * every panel.
 *
 * @api
 */
final class SecuritySettings extends Settings
{
    /** After the password, ask for a code sent by email (see TwoFactorLogin). */
    public bool $twoFactor = false;

    public static function key(): string
    {
        return 'security';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Toggle::make('twoFactor')->label('Two-factor login')
                ->help('After the password, everyone also types a 6-digit code we email them. Make sure the app can send email first: without it, nobody can log in (php artisan sunrice:two-factor off turns it off again).'),
        ]);
    }
}
