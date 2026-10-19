<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

/** Everyone may see products; locked ones can't be edited; none deleted. */
final class ProductPolicy
{
    public function viewAny(mixed $user): bool
    {
        return true;
    }

    public function view(mixed $user, Product $product): bool
    {
        return true;
    }

    public function create(mixed $user): bool
    {
        return false;
    }

    public function update(mixed $user, Product $product): bool
    {
        return $product->name !== 'Locked';
    }

    public function delete(mixed $user, Product $product): bool
    {
        return false;
    }
}
