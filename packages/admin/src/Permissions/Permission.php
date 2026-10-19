<?php

declare(strict_types=1);

namespace Sunrice\Admin\Permissions;

use InvalidArgumentException;

/**
 * A permission roles can be given, shown in the role editor under its
 * group. Names are dotted and lowercase: "shop.orders.refund".
 *
 * @api
 */
final readonly class Permission
{
    private function __construct(
        public string $name,
        public string $label,
        public string $group,
    ) {}

    public static function make(string $name, string $label, string $group = 'Other'): self
    {
        if (preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*(?:\.[a-z0-9]+(?:[-_][a-z0-9]+)*)+$/', $name) !== 1) {
            throw new InvalidArgumentException("Permission \"{$name}\": use lowercase dotted names like \"shop.orders.refund\".");
        }
        if (trim($label) === '') {
            throw new InvalidArgumentException("Permission \"{$name}\" needs a label.");
        }

        return new self($name, $label, $group);
    }

    /** The part before the first dot: the owner's prefix. */
    public function prefix(): string
    {
        return explode('.', $this->name, 2)[0];
    }
}
