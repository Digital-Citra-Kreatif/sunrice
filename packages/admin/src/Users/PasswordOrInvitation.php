<?php

declare(strict_types=1);

namespace Sunrice\Admin\Users;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * New user: a password, or none and "Email an invitation" on. Said in
 * words, not as Laravel's "required unless invite is in true, 1".
 *
 * @internal
 */
final class PasswordOrInvitation implements DataAwareRule, ValidationRule
{
    /** Runs on an empty password too. */
    public bool $implicit = true;

    /** @var array<mixed> */
    private array $data = [];

    /** @param array<mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $empty = $value === null || (is_string($value) && trim($value) === '');
        if ($empty && ! filter_var($this->data['invite'] ?? false, FILTER_VALIDATE_BOOL)) {
            $fail(__('Type a password, or turn on Email an invitation.'));
        }
    }
}
