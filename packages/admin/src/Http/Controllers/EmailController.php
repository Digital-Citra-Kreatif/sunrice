<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Sunrice\Admin\Branding\Branding;
use Sunrice\Admin\Mail\TestEmail;
use Sunrice\Admin\Panel;
use Throwable;

/**
 * Administration → Email: how the app sends email, and a test send.
 *
 * @internal
 */
final class EmailController
{
    /** Mailers that keep messages instead of sending them. */
    private const NOT_SENDING = ['log', 'array'];

    public function index(Request $request): Response
    {
        [$mailer, $transport] = self::mailer();
        $host = config("mail.mailers.{$mailer}.host");
        $port = config("mail.mailers.{$mailer}.port");

        return Inertia::render('Email/Index', [
            'mailer' => $mailer,
            'transport' => $transport,
            'server' => $transport === 'smtp' && is_string($host) ? $host.(is_scalar($port) ? ':'.$port : '') : null,
            'from' => ['address' => self::string(config('mail.from.address')), 'name' => self::string(config('mail.from.name'))],
            'sends' => ! in_array($transport, self::NOT_SENDING, true),
            'to' => self::string(data_get($request->user(), 'email')),
        ]);
    }

    public function store(Request $request, Panel $panel): RedirectResponse
    {
        $request->validate(['to' => ['required', 'email:filter', 'max:255']]);
        $to = $request->string('to')->toString();
        try {
            Mail::to($to)->send(new TestEmail(Branding::shared($panel)['brand'], self::string(data_get($request->user(), 'name')) ?? 'Someone'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'The email could not be sent: '.$e->getMessage());
        }

        return back()->with('success', in_array(self::mailer()[1], self::NOT_SENDING, true)
            ? "Wrote the test email to {$to} to the log: this mailer doesn't send."
            : "Sent a test email to {$to}.");
    }

    /** @return array{string, string} the default mailer and its transport */
    private static function mailer(): array
    {
        $mailer = config('mail.default');
        $mailer = is_string($mailer) ? $mailer : 'smtp';
        $transport = config("mail.mailers.{$mailer}.transport");

        return [$mailer, is_string($transport) ? $transport : $mailer];
    }

    private static function string(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
