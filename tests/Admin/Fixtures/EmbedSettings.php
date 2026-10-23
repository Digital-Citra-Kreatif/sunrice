<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Sunrice\Admin\Resources\Forms\Code;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Settings\Settings;

/** A section with a code field, a warning, and a config switch that can turn it off. */
final class EmbedSettings extends Settings
{
    public string $widget = '';

    public static function key(): string
    {
        return 'embed';
    }

    public static function available(): bool
    {
        return config('shop.embed', true) === true;
    }

    public static function warning(): string
    {
        return 'Scripts here run for every customer.';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([Code::make('widget')->language('javascript')->lines(3, 12)->maxLength(100)]);
    }
}
