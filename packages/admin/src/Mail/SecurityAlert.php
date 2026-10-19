<?php

declare(strict_types=1);

namespace Sunrice\Admin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A security alert: what happened, why it may matter and what to do.
 *
 * @internal
 */
final class SecurityAlert extends Mailable
{
    use Queueable;

    /** @param array<string, string> $details label => value */
    public function __construct(
        public readonly string $headline,
        public readonly string $what,
        public readonly string $advice,
        public readonly array $details,
        public readonly string $brand,
        public readonly string $logUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->brand}] Security: {$this->headline}");
    }

    public function content(): Content
    {
        return new Content(view: 'sunrice-admin::mail.security-alert', text: 'sunrice-admin::mail.security-alert-text');
    }
}
