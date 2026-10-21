<?php

declare(strict_types=1);

namespace Sunrice\Admin\Actions\Preferences;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Preferences\Preferences;
use Sunrice\Admin\Support\PanelLocale;

/**
 * A user's panel language (one of sunrice-admin.locales), kept as their
 * preference.
 *
 * @api
 */
final class SetPanelLanguage
{
    public function __construct(private Preferences $preferences) {}

    /** @throws ValidationException */
    public function handle(Authenticatable $user, mixed $locale): string
    {
        if (! PanelLocale::valid($locale)) {
            throw ValidationException::withMessages(['locale' => __('Choose one of the panel\'s languages.')]);
        }
        /** @var string $locale */
        $this->preferences->set($user, PanelLocale::PREFERENCE, $locale);

        return $locale;
    }
}
