<?php

declare(strict_types=1);

namespace Sunrice\Tests\Admin\Fixtures;

use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Settings\Settings;

final class MailSettings extends Settings
{
    public string $from = 'shop@example.com';

    public static function key(): string
    {
        return 'outbox';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([TextInput::make('from')->email()->required()]);
    }
}
