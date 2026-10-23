<?php

declare(strict_types=1);

namespace Sunrice\Admin\Auth;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The strong password rule (PasswordPolicy::rules()): each thing the
 * password lacks is its own message.
 *
 * @internal
 */
final class StrongPassword implements DataAwareRule, ValidationRule
{
    /** @var array<mixed> */
    private array $data = [];

    public function __construct(private readonly ?Authenticatable $for = null) {}

    /** @param array<mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return; // 'string' says it
        }
        foreach (PasswordPolicy::problems($value, $this->for, null, $this->data) as $message) {
            $fail($message);
        }
    }
}
