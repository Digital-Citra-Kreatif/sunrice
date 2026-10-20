<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sunrice\Admin\Actions\Preferences\SetPanelLanguage;
use Sunrice\Admin\Support\PanelLocale;

/**
 * The panel language a user picks (their name in the sidebar → Language):
 * kept for them, and on this browser for the sign-in page.
 *
 * @internal
 */
final class LanguageController
{
    public function update(Request $request, SetPanelLanguage $set): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Authenticatable, 403);
        $locale = $set->handle($user, $request->input('locale'));

        return back()->withCookie(cookie()->forever(PanelLocale::COOKIE, $locale));
    }
}
