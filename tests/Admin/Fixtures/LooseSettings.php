<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Settings\Settings;

/** Settings the store refuses: a property without a type. */
final class LooseSettings extends Settings
{
    public $loose = 1;

    public static function key(): string
    {
        return 'loose';
    }

    public static function form(Form $form): Form
    {
        return $form;
    }
}
