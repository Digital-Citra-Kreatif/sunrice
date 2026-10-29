<?php

declare(strict_types=1);

namespace Sunrice\Admin\Settings;

use Illuminate\Validation\Rule;
use Sunrice\Admin\Resources\Forms\Field;
use Sunrice\Admin\Support\Say;

/**
 * Settings → Branding → Sidebar shows: one of BrandingSettings::DISPLAYS,
 * each choice drawn as the sidebar's header with the form's current name,
 * logo and icon (from `sunrice.admin.branding.preview`).
 *
 * @internal
 */
final class BrandDisplayChoice extends Field
{
    /** What each choice is for, under its label. */
    private const DESCRIPTIONS = [
        'auto' => 'A wide logo instead of the name, a square one beside it.',
        'logo_name' => 'The logo (or the icon) beside the name.',
        'logo' => 'The logo alone, for a wordmark that holds the name.',
        'icon_name' => 'The browser icon beside the name, for a wide logo with the name written out.',
        'name' => 'The name and tagline, no image.',
    ];

    public function type(): string
    {
        return 'brand_display';
    }

    protected function typeRules(): array
    {
        return ['string', Rule::in(array_keys(BrandingSettings::DISPLAYS))];
    }

    protected function props(): array
    {
        $options = [];
        foreach (BrandingSettings::DISPLAYS as $value => $label) {
            $options[] = ['value' => $value, 'label' => Say::it($label), 'description' => Say::it(self::DESCRIPTIONS[$value])];
        }

        return ['options' => $options, 'previewUrl' => route('sunrice.admin.branding.preview')];
    }
}
