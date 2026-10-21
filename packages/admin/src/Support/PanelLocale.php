<?php

declare(strict_types=1);

namespace Sunrice\Admin\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Sunrice\Admin\Preferences\Preferences;

/**
 * The panel's language: each user's pick (a preference), else the one last
 * picked on this browser (a cookie, for the sign-in page), else
 * sunrice-admin.locale. Only the panel's words: the website's content
 * languages are the CMS's own.
 *
 * @api
 */
final class PanelLocale
{
    public const PREFERENCE = 'locale';

    public const COOKIE = 'sunrice_locale';

    /** Where a user's pick is kept for the session. */
    public const SESSION = 'sunrice.locale';

    public function __construct(private Preferences $preferences) {}

    /** @return array<string, string> code => name, from sunrice-admin.locales */
    public static function available(): array
    {
        $locales = config('sunrice-admin.locales');
        $available = [];
        foreach (is_array($locales) ? $locales : [] as $code => $name) {
            if (is_string($code) && is_string($name) && preg_match('/^[a-z]{2,3}(?:[_-][A-Za-z]{2,4})?$/', $code) === 1) {
                $available[$code] = $name;
            }
        }

        return $available === [] ? ['en' => 'English'] : $available;
    }

    public static function default(): string
    {
        $default = config('sunrice-admin.locale');
        $available = self::available();

        return is_string($default) && isset($available[$default]) ? $default : (string) array_key_first($available);
    }

    /** @phpstan-assert-if-true string $code */
    public static function valid(mixed $code): bool
    {
        return is_string($code) && isset(self::available()[$code]);
    }

    /** Where a user's pick is kept in the session. */
    public static function sessionKey(Authenticatable $user): string
    {
        $id = $user->getAuthIdentifier();

        return self::SESSION.'.'.(is_scalar($id) ? (string) $id : '');
    }

    /** The language for this request. */
    public function resolve(Request $request): string
    {
        $user = $request->user();
        if ($user instanceof Authenticatable) {
            // Read once per session, not on every page (SetPanelLanguage keeps it in step).
            $key = self::sessionKey($user);
            $picked = $request->hasSession() ? $request->session()->get($key) : null;
            if (! is_string($picked)) {
                $picked = $this->preferences->get($user, self::PREFERENCE);
                $picked = is_string($picked) ? $picked : '';
                if ($request->hasSession()) {
                    $request->session()->put($key, $picked);
                }
            }
            if (isset(self::available()[$picked])) {
                return $picked;
            }
        }
        $cookie = $request->cookie(self::COOKIE);

        return self::valid($cookie) ? $cookie : self::default();
    }

    /**
     * The words in a language, for the panel's frontend (t()): every JSON
     * translation Laravel knows for it, the panel's, its plugins' and the
     * app's own. Nothing for English: the words are the keys.
     *
     * @return array<string, string>
     */
    public static function dictionary(string $locale): array
    {
        $lines = app('translator')->getLoader()->load($locale, '*', '*');

        return array_filter($lines, fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH);
    }
}
