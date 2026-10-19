<?php

declare(strict_types=1);

namespace Sunrice\Tests\Cms\Fixtures;

use Sunrice\Cms\Fields\Field;
use Sunrice\Cms\Fields\FieldType;

final class ColorField extends FieldType
{
    public function type(): string
    {
        return 'color';
    }

    public function rules(Field $field): array
    {
        return ['string', 'regex:/^#[0-9a-f]{6}$/i'];
    }

    public function normalize(mixed $value, Field $field): mixed
    {
        return is_string($value) && $value !== '' ? strtolower($value) : null;
    }
}
