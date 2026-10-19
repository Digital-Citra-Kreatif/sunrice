<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\MediaPicker;
use Sunrice\Admin\Settings\Settings;

final class BrandSettings extends Settings
{
    public ?int $logo = null;

    /** @var list<int> */
    public array $gallery = [];

    public static function key(): string
    {
        return 'brand';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            MediaPicker::make('logo')->images(),
            MediaPicker::make('gallery')->images()->multiple(2),
        ]);
    }
}
