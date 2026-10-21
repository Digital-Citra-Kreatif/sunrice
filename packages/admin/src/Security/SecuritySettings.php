<?php

declare(strict_types=1);

namespace Sunrice\Admin\Security;

use Spatie\Permission\Models\Role;
use Sunrice\Admin\Actions\Permissions\SyncPermissions;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\CheckboxList;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Textarea;
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

    /** @var list<string> roles whose people get security alerts, besides super admins and config's */
    public array $alertRoles = [];

    /** More addresses for security alerts, one per line. */
    public string $alertEmails = '';

    public static function key(): string
    {
        return 'security';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Signing in')->schema([
                Toggle::make('twoFactor')->label('Two-factor login')
                    ->help('After the password, everyone also types a 6-digit code we email them. Make sure the app can send email first (Settings → Mail → Send a test email): without it, nobody can log in. If that happens, a developer can turn it off on the server (see Help → Guide → Activity log and security).'),
            ]),
            Section::make('Security alerts')->schema([
                CheckboxList::make('alertRoles')->label('Also send alerts to these roles')
                    ->options(fn (): array => self::roles())
                    ->help('Super admins always get them.'),
                Textarea::make('alertEmails')->label('And to these addresses')->rows(3)->maxLength(2000)->placeholder('security@example.com')
                    ->rules([function (string $attribute, mixed $value, \Closure $fail): void {
                        if (is_string($value) && count(self::emails($value)) !== count(array_filter(preg_split('/[\s,;]+/', $value) ?: []))) {
                            $fail('Write one email address per line.');
                        }
                    }])
                    ->help('One per line, e.g. whoever looks after the server.'),
            ]),
        ]);
    }

    /** @return array<string, string> the roles to choose from (super admins get alerts anyway) */
    private static function roles(): array
    {
        $roles = [];
        foreach (Role::query()->where('guard_name', app(Panel::class)->getGuard())->orderBy('name')->pluck('name') as $name) {
            if (is_string($name) && $name !== SyncPermissions::superAdminRole()) {
                $roles[$name] = $name;
            }
        }

        return $roles;
    }

    /**
     * The valid addresses in a list typed one per line (or with commas).
     *
     * @return list<string>
     */
    public static function emails(string $list): array
    {
        return array_values(array_filter(preg_split('/[\s,;]+/', $list) ?: [], fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false));
    }
}
