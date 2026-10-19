<?php

declare(strict_types=1);

namespace Sunrice\Admin\Resources\Forms;

/**
 * Something a resource form is made of: a field, or a layout holding
 * fields (Section).
 *
 * @api
 */
interface Component
{
    /** Whether it appears on the create or the edit form. */
    public function shownOn(string $operation): bool;

    /** @return list<Field> the fields in it */
    public function fields(): array;

    /** @return array<string, mixed> its description for the React form */
    public function toArray(): array;
}
