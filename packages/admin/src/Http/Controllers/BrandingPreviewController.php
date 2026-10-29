<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Admin\Support\Branding;

/**
 * Settings → Branding's preview: the branding as it would be shown with
 * the form's unsaved name, logo, icon and colour, and what each "Sidebar
 * shows" choice resolves to. Worked out by Support\Branding, the same
 * code as the sidebar and the favicon. Saves nothing. Guarded by
 * sunrice.settings.branding (the route's middleware).
 *
 * @internal
 */
final class BrandingPreviewController
{
    public function __invoke(Request $request, Panel $panel): JsonResponse
    {
        /** @var array{name?: string|null, tagline?: string|null, logo?: int|string|null, favicon?: int|string|null, color?: string|null} $input */
        $input = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'tagline' => ['nullable', 'string', 'max:80'],
            'logo' => ['nullable', 'integer'],
            'favicon' => ['nullable', 'integer'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $settings = (new BrandingSettings)->fill([
            'name' => $input['name'] ?? '',
            'tagline' => $input['tagline'] ?? '',
            'logo' => isset($input['logo']) ? (int) $input['logo'] : null,
            'favicon' => isset($input['favicon']) ? (int) $input['favicon'] : null,
            'color' => $input['color'] ?? '',
        ]);
        $branding = new Branding($panel, $settings);
        $shown = $branding->toArray();

        $displays = [];
        foreach (array_keys(BrandingSettings::DISPLAYS) as $choice) {
            $displays[$choice] = Branding::resolveDisplay($choice, $shown['logo'] !== null, $shown['logoWide']);
        }

        return response()->json([
            'name' => $shown['name'],
            'tagline' => $shown['tagline'],
            'logo' => $shown['logo'],
            'logoWide' => $shown['logoWide'],
            'mark' => $shown['mark'],
            'lettermark' => $shown['lettermark'],
            'color' => $shown['color'],
            'displays' => $displays,
        ]);
    }
}
