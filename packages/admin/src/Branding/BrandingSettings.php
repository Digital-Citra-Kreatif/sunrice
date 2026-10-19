<?php

declare(strict_types=1);

namespace Sunrice\Admin\Branding;

use Sunrice\Admin\Media\MediaUsages;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\MediaPicker;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Forms\TextInput;
use Sunrice\Admin\Settings\Settings;

/**
 * Settings → Branding, guarded by sunrice.settings.branding: the panel's
 * name, logo and colour, and what the sign-in page says. Part of every
 * panel; empty values keep the panel's own ($panel->brand()).
 *
 * @api
 */
final class BrandingSettings extends Settings
{
    public string $name = '';

    /** A media id: an image. */
    public ?int $logo = null;

    /** "#0f766e"; empty: the panel's own. */
    public string $color = '';

    public string $loginHeadline = '';

    public string $loginText = '';

    /** Saved, with the media library's "Used by" for the logo. */
    public function save(): void
    {
        parent::save();
        app(MediaUsages::class)->sync('sunrice.branding', 'logo', $this->logo === null ? [] : [$this->logo], 'Branding: logo', '/'.app(Panel::class)->getPath().'/settings/branding');
    }

    public static function key(): string
    {
        return 'branding';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('The panel')->columns(2)->schema([
                TextInput::make('name')->label('Name')->maxLength(60)->placeholder(app(Panel::class)->getBrand())
                    ->help('In the sidebar, the sign-in pages and the browser tab.'),
                TextInput::make('color')->label('Colour')->maxLength(7)->placeholder('#0f766e')->rules(['regex:/^#[0-9a-fA-F]{6}$/'])
                    ->help('Buttons and highlights, as a hex colour.'),
                MediaPicker::make('logo')->label('Logo')->images()->span(2)
                    ->help('A square image works best; it replaces the initial.'),
            ]),
            Section::make('Sign-in page')->columns(2)->schema([
                TextInput::make('loginHeadline')->label('Headline')->maxLength(80)->placeholder('Sign in')->span(2),
                Textarea::make('loginText')->label('Text')->rows(2)->maxLength(300)->span(2)
                    ->help('Under the headline, e.g. who to ask for an account.'),
            ]),
        ]);
    }
}
