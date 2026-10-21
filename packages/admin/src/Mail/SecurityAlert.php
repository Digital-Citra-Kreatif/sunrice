<?php

declare(strict_types=1);

namespace Sunrice\Admin\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Sunrice\Admin\Support\Say;

/**
 * A security alert: what happened, why it may matter and what to do.
 *
 * @internal
 */
final class SecurityAlert extends Mailable
{
    use Queueable;

    /**
     * The texts are English; the mail says them in the app's language when
     * it's sent.
     *
     * @param  array<string, string>  $details  label => value
     * @param  string  $words  `$what` as written, with placeholders (":name"); empty when `$what` has none
     * @param  array<string, string>  $replace  the placeholders' values
     */
    public function __construct(
        public readonly string $headline,
        public readonly string $what,
        public readonly string $advice,
        public readonly array $details,
        public readonly string $brand,
        public readonly string $logUrl,
        public readonly string $words = '',
        public readonly array $replace = [],
    ) {}

    public function envelope(): Envelope
    {
        $subject = __('[:brand] Security: :headline', ['brand' => $this->brand, 'headline' => Say::it($this->headline)]);

        return new Envelope(subject: is_string($subject) ? $subject : "[{$this->brand}] Security: {$this->headline}");
    }

    public function content(): Content
    {
        $details = [];
        foreach ($this->details as $label => $value) {
            $details[Say::it($label)] = $value;
        }
        $what = $this->words === '' ? Say::it($this->what) : __($this->words, $this->replace);

        return new Content(view: 'sunrice-admin::mail.security-alert', text: 'sunrice-admin::mail.security-alert-text', with: [
            'saidHeadline' => Say::it($this->headline),
            'saidWhat' => is_string($what) ? $what : $this->what,
            'saidAdvice' => Say::it($this->advice),
            'saidDetails' => $details,
        ]);
    }
}
