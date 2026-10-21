<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;

/**
 * A one-line text input: ->email(), ->url(), ->color(), ->maxLength(100),
 * ->slugFrom('title').
 *
 * @api
 */
final class TextInput extends Field
{
    private string $inputType = 'text';

    private int $maxLength = 255;

    private ?string $slugFrom = null;

    /** @var '_'|'-' */
    private string $slugSeparator = '_';

    public function type(): string
    {
        return 'text';
    }

    public function email(): self
    {
        $this->inputType = 'email';

        return $this;
    }

    public function url(): self
    {
        $this->inputType = 'url';

        return $this;
    }

    /** A colour as #rrggbb, with a colour picker beside the text. */
    public function color(): self
    {
        $this->inputType = 'color';
        $this->maxLength = 7;

        return $this;
    }

    /**
     * While creating, fill it from another field as that one is typed,
     * made fit for a handle or an address ("Main menu" → "main_menu", or
     * with '-' "main-menu"), until someone types in this one. What's typed
     * in it is kept to lowercase letters, digits and the separator; add the
     * rule that checks it on the server too.
     *
     * @param  '_'|'-'  $separator
     */
    public function slugFrom(string $field, string $separator = '_'): self
    {
        $this->slugFrom = $field;
        $this->slugSeparator = $separator;

        return $this;
    }

    public function maxLength(int $length): self
    {
        $this->maxLength = $length;

        return $this;
    }

    protected function typeRules(): array
    {
        if ($this->inputType === 'color') {
            // One message that shows the format, whatever is wrong with it.
            return ['string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || preg_match('/^#[0-9a-fA-F]{6}$/', $value) !== 1) {
                    $fail(__('The :attribute must be a colour written as # and six hex digits, like #1e66f5.'));
                }
            }];
        }

        return [
            'string',
            "max:{$this->maxLength}",
            ...match ($this->inputType) {
                'email' => ['email'],
                'url' => ['url:http,https'],
                default => [],
            },
        ];
    }

    protected function props(): array
    {
        return ['inputType' => $this->inputType, 'maxLength' => $this->maxLength]
            + ($this->slugFrom !== null ? ['slugFrom' => $this->slugFrom, 'slugSeparator' => $this->slugSeparator] : []);
    }
}
