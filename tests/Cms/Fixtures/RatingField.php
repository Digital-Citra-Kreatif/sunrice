<?php

declare(strict_types=1);

namespace Sunrice\Tests\Cms\Fixtures;

use Sunrice\Cms\Fields\CustomField;

final class RatingField extends CustomField
{
    public function type(): string
    {
        return 'rating';
    }

    public function baseType(): string
    {
        return 'number';
    }

    public function preset(): array
    {
        return ['min' => 1, 'max' => 5];
    }

    public function extraRules(): array
    {
        return ['integer'];
    }
}
