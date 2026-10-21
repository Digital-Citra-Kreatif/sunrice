<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

use Closure;

/**
 * A one-line text input: ->email(), ->url(), ->color(), ->maxLength(100).
 *
 * @api
 */
final class TextInput extends Field
{
    private string $inputType = 'text';

    private int $maxLength = 255;

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
                    $fail('The :attribute must be a colour written as # and six hex digits, like #1e66f5.');
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
        return ['inputType' => $this->inputType, 'maxLength' => $this->maxLength];
    }
}
