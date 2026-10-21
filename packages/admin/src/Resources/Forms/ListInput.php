<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;
use Sunrice\Admin\Support\Say;

/**
 * A list of short values, one input per row with "Add" and remove
 * buttons, kept as text with one value per line (what a "one per line"
 * textarea held, so stored values don't change):
 *
 *     ListInput::make('sameAs')->urls(httpsOnly: true)->networks()->addLabel('Add profile')
 *     ListInput::make('alertEmails')->emails()->max(20)
 *
 * ->urls() takes web addresses (http or https, or https only), ->emails()
 * email addresses (a list typed with commas between them is split too);
 * each value is checked, and the error names the one that isn't. ->networks() shows which site an address is on (Instagram,
 * LinkedIn, X…). Saved without empty lines, each value trimmed.
 *
 * @api
 */
final class ListInput extends Field
{
    private ?string $kind = null;

    private bool $httpsOnly = false;

    private bool $networks = false;

    private string $addLabel = 'Add';

    private ?int $max = null;

    private int $maxLength = 5000;

    public function type(): string
    {
        return 'list';
    }

    /** Each value a web address: http or https, or https only. */
    public function urls(bool $httpsOnly = false): self
    {
        $this->kind = 'url';
        $this->httpsOnly = $httpsOnly;

        return $this;
    }

    /** Each value an email address. */
    public function emails(): self
    {
        $this->kind = 'email';

        return $this;
    }

    /** Show the site each address is on (with ->urls()): Instagram, LinkedIn, Facebook, X, YouTube, TikTok, Wikipedia… */
    public function networks(bool $networks = true): self
    {
        $this->networks = $networks;

        return $this;
    }

    /** The add button's label ("Add profile"). */
    public function addLabel(string $label): self
    {
        $this->addLabel = $label;

        return $this;
    }

    /** At most this many values. */
    public function max(?int $max): self
    {
        $this->max = $max;

        return $this;
    }

    /** The longest the whole list may be, in characters (5000 by default). */
    public function maxLength(int $length): self
    {
        $this->maxLength = $length;

        return $this;
    }

    /**
     * The values in a list's text, trimmed, without empty lines.
     *
     * @return list<string>
     */
    public static function items(mixed $value, bool $emails = false): array
    {
        // Addresses can't hold commas, semicolons or spaces: a list of them may have been typed with those between.
        $lines = is_array($value) ? array_map(fn (mixed $line): string => is_scalar($line) ? (string) $line : '', $value) : preg_split($emails ? '/[\s,;]+/' : '/\R/', is_string($value) ? $value : '');

        return array_values(array_filter(array_map(trim(...), $lines ?: []), fn (string $line): bool => $line !== ''));
    }

    protected function typeRules(): array
    {
        return ['string', "max:{$this->maxLength}", function (string $attribute, mixed $value, Closure $fail): void {
            $items = self::items($value, $this->kind === 'email');
            if ($this->max !== null && count($items) > $this->max) {
                $fail(self::said('Use at most :max.', ['max' => (string) $this->max]));

                return;
            }
            foreach ($items as $item) {
                $message = $this->problem($item);
                if ($message !== null) {
                    $fail($message);

                    return;
                }
            }
        }];
    }

    /** What's wrong with one value, or null. */
    private function problem(string $item): ?string
    {
        return match ($this->kind) {
            'url' => filter_var($item, FILTER_VALIDATE_URL) === false || preg_match($this->httpsOnly ? '#^https://#i' : '#^https?://#i', $item) !== 1
                ? self::said($this->httpsOnly ? '":item" isn\'t a web address starting with https://.' : '":item" isn\'t a web address (https://…).', ['item' => $item])
                : null,
            'email' => filter_var($item, FILTER_VALIDATE_EMAIL) === false ? self::said('":item" isn\'t an email address.', ['item' => $item]) : null,
            default => null,
        };
    }

    /** @param array<string, string> $replace */
    private static function said(string $text, array $replace): string
    {
        $said = __($text, $replace);

        return is_string($said) ? $said : $text;
    }

    public function dehydrate(mixed $value): mixed
    {
        return $value === null ? null : implode("\n", self::items($value, $this->kind === 'email'));
    }

    protected function props(): array
    {
        return [
            'accepts' => $this->kind,
            'httpsOnly' => $this->httpsOnly,
            'networks' => $this->networks,
            'addLabel' => Say::it($this->addLabel),
            'max' => $this->max,
        ];
    }
}
