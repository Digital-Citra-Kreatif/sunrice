<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use Sunrice\Admin\Panel;
use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Resources\Forms\MediaPicker;
use Sunrice\Admin\Resources\Forms\Section;
use Sunrice\Admin\Resources\Forms\Select;
use Sunrice\Admin\Resources\Forms\Textarea;
use Sunrice\Admin\Resources\Forms\TextInput;

/**
 * Settings → Branding, guarded by sunrice.settings.branding. Part of
 * every panel: the name, tagline, logo, font and colour of the panel and
 * the wording of its sign-in page. Empty values fall back to the panel's
 * own (`Panel::brand()`, `sunrice-admin.font`, the neutral palette and
 * the built-in sign-in copy); `Sunrice\Admin\Support\Branding` resolves
 * them.
 *
 * @api
 */
final class BrandingSettings extends Settings
{
    /**
     * Fonts offered: key => [family, Bunny Fonts slug]. A null slug loads
     * nothing (the operating system's own font).
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    public const FONTS = [
        'inter' => ['Inter', 'inter'],
        'instrument-sans' => ['Instrument Sans', 'instrument-sans'],
        'manrope' => ['Manrope', 'manrope'],
        'dm-sans' => ['DM Sans', 'dm-sans'],
        'plus-jakarta-sans' => ['Plus Jakarta Sans', 'plus-jakarta-sans'],
        'figtree' => ['Figtree', 'figtree'],
        'outfit' => ['Outfit', 'outfit'],
        'ibm-plex-sans' => ['IBM Plex Sans', 'ibm-plex-sans'],
        'source-sans-3' => ['Source Sans 3', 'source-sans-3'],
        'nunito-sans' => ['Nunito Sans', 'nunito-sans'],
        'system' => ['System', null],
    ];

    /** Sign-in page layouts: a side panel next to the form, or the form alone. */
    public const LOGIN_LAYOUTS = ['split' => 'Side panel next to the form', 'centered' => 'Form only, centered'];

    /** The sign-in page's built-in wording, used for the texts left empty. */
    public const LOGIN_DEFAULTS = [
        'eyebrow' => 'A little space to create',
        'headline' => "Good content.\nBeautifully managed.",
        'text' => 'One calm workspace for your content, your team, and everything you’re ready to share.',
        'footer' => 'Your content, in good hands.',
    ];

    /** Shown in the sidebar, on the sign-in page and in browser tabs; empty: the panel's brand. */
    public string $name = '';

    public string $tagline = '';

    /** A media id (an image); without one the sidebar shows the name's first letter. */
    public ?int $logo = null;

    /** A key of FONTS; empty: the `sunrice-admin.font` config. */
    public string $font = '';

    /** #rrggbb for buttons, the active menu item and focus rings; empty: the neutral palette. */
    public string $color = '';

    public string $loginLayout = 'split';

    public string $loginEyebrow = '';

    public string $loginHeadline = '';

    public string $loginText = '';

    public string $loginFooter = '';

    /** A media id (an image) shown in the side panel instead of the built-in artwork. */
    public ?int $loginImage = null;

    public static function key(): string
    {
        return 'branding';
    }

    public static function description(): string
    {
        return 'How the admin panel and its sign-in page look. It doesn\'t change the website.';
    }

    public static function form(Form $form): Form
    {
        $fonts = [];
        foreach (self::FONTS as $key => [$family]) {
            $fonts[$key] = $family;
        }

        return $form->schema([
            Section::make('Panel')->columns(2)->schema([
                TextInput::make('name')->label('Panel name')->maxLength(60)->placeholder(app(Panel::class)->getBrand()),
                TextInput::make('tagline')->maxLength(80)->placeholder('Shown under the name'),
                MediaPicker::make('logo')->images()
                    ->help('A square SVG or PNG works best. Without one, the sidebar shows the name\'s first letter.'),
                Select::make('font')->options($fonts)->placeholder('Default')
                    ->help('The typeface of the admin. Empty: the default font.'),
                TextInput::make('color')->label('Brand colour')->color()->placeholder('#1e66f5')
                    ->help('Buttons, the active menu item and focus rings. Text on it turns black or white, whichever reads better. Empty: neutral.'),
            ]),
            Section::make('Sign-in page')->description('Texts left empty keep the built-in wording.')->columns(2)->schema([
                Select::make('loginLayout')->label('Layout')->options(self::LOGIN_LAYOUTS)->required(),
                TextInput::make('loginEyebrow')->label('Small heading')->maxLength(80)->placeholder(self::LOGIN_DEFAULTS['eyebrow']),
                Textarea::make('loginHeadline')->label('Headline')->rows(2)->maxLength(160)
                    ->placeholder(self::LOGIN_DEFAULTS['headline'])->help('Each new line starts a lighter line.'),
                Textarea::make('loginText')->label('Text')->rows(2)->maxLength(300)->placeholder(self::LOGIN_DEFAULTS['text']),
                TextInput::make('loginFooter')->label('Note at the bottom')->maxLength(120)->placeholder(self::LOGIN_DEFAULTS['footer']),
                MediaPicker::make('loginImage')->label('Image')->images()
                    ->help('Shown in the side panel instead of the built-in artwork.'),
            ]),
        ]);
    }
}
