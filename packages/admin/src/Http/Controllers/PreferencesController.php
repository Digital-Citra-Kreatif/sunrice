<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sunrice\Admin\Preferences\Preferences;

/**
 * The signed-in user's own preferences as JSON (useTablePreferences):
 * anyone with panel access reads and writes their own, never another's.
 *
 * @internal
 */
final class PreferencesController
{
    public function __construct(private readonly Preferences $preferences) {}

    public function show(Request $request, string $key): JsonResponse
    {
        return response()->json(['key' => $key, 'value' => $this->preferences->get(self::user($request), $key)]);
    }

    /** `{"value": …}`: kept as it is; null forgets it. */
    public function update(Request $request, string $key): JsonResponse
    {
        $request->validate(['value' => ['present']]);
        $value = $request->input('value');
        if (strlen((string) json_encode($value)) > Preferences::MAX_BYTES) {
            throw ValidationException::withMessages(['value' => __('That is too much to keep as a preference.')]);
        }
        $this->preferences->set(self::user($request), $key, $value);

        return response()->json(['key' => $key, 'value' => $value]);
    }

    private static function user(Request $request): Authenticatable
    {
        $user = $request->user();
        abort_unless($user instanceof Authenticatable, 403);

        return $user;
    }
}
