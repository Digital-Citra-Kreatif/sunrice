<?php

declare(strict_types=1);

namespace Sunrice\Admin\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * The test email the Email page sends.
 *
 * @internal
 */
final class TestEmail extends Mailable
{
    public function __construct(public readonly string $brand, public readonly string $sender) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->brand}] Test email");
    }

    public function content(): Content
    {
        return new Content(text: 'sunrice-admin::mail.test-email');
    }
}
