<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Support\Card;

/**
 * Settings → Mail, guarded by sunrice.settings.mail: how the app sends
 * mail (from its config, nothing to edit here) and a button that sends a
 * test email to the signed-in user. Part of every panel.
 *
 * @internal
 */
final class MailSettings extends Settings
{
    public static function key(): string
    {
        return 'mail';
    }

    public static function description(): string
    {
        return 'The panel emails login codes, password resets and security alerts. Mail is set up in the app\'s .env (config/mail.php).';
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function cards(Authorizable $user): array
    {
        $info = self::info();
        $card = Card::make('How mail is sent')->rows([
            'Mailer' => $info['mailer'].($info['transport'] !== $info['mailer'] ? " ({$info['transport']})" : ''),
            'Server' => $info['host'] === null ? null : $info['host'].($info['port'] === null ? '' : ':'.$info['port']),
            'From' => $info['from'] === null ? __('Not set (MAIL_FROM_ADDRESS)') : trim(($info['from_name'] ?? '').' <'.$info['from'].'>'),
            'Queue connection' => $info['queue'] === 'sync'
                ? __(':queue (queued mail is sent during the request)', ['queue' => $info['queue']])
                : __(':queue (queued mail needs a running queue worker)', ['queue' => $info['queue']]),
        ]);
        if (! $info['delivers']) {
            $card->notice(__('The :mailer mailer doesn\'t deliver email: messages only go to the log. Nobody receives login codes, password resets or alerts.', ['mailer' => $info['transport']]), Card::WARNING);
        }
        $email = data_get($user, 'email');
        $card->button('Send a test email', route('sunrice.admin.settings.mail.test', [], false), is_string($email)
            ? __('Send a test email to :email now?', ['email' => $email])
            : __('Send a test email to your address now?'));

        return [$card];
    }

    /**
     * How mail is configured, without passwords.
     *
     * @return array{mailer: string, transport: string, host: string|null, port: string|null, from: string|null, from_name: string|null, queue: string, delivers: bool}
     */
    public static function info(): array
    {
        $text = fn (mixed $value): ?string => is_scalar($value) && (string) $value !== '' ? (string) $value : null;
        $mailer = $text(config('mail.default')) ?? 'smtp';
        $transport = $text(config("mail.mailers.{$mailer}.transport")) ?? $mailer;

        return [
            'mailer' => $mailer,
            'transport' => $transport,
            'host' => $transport === 'smtp' ? $text(config("mail.mailers.{$mailer}.host")) : null,
            'port' => $transport === 'smtp' ? $text(config("mail.mailers.{$mailer}.port")) : null,
            'from' => $text(config('mail.from.address')),
            'from_name' => $text(config('mail.from.name')),
            'queue' => $text(config('queue.default')) ?? 'sync',
            'delivers' => ! in_array($transport, ['log', 'array'], true),
        ];
    }
}
