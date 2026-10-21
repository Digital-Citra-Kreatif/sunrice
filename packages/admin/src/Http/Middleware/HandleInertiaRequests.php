<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Panel;
use Sunrice\Admin\Support\Branding;

/**
 * Inertia for the panel's pages: its root view, its asset version and the
 * data every page receives.
 *
 * @internal
 */
final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'sunrice-admin::app';

    public function __construct(private Panel $panel) {}

    public function version(Request $request): ?string
    {
        return AdminAssets::version();
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'sunrice' => fn (): array => [
                'brand' => ($branding = Branding::make()->toArray())['name'],
                'branding' => $branding,
                'path' => '/'.$this->panel->getPath(),
                'bundles' => array_values(array_map(fn (PluginBundle $bundle) => $bundle->toArray(), $this->panel->getBundles())),
                'commandSearches' => $user === null ? [] : $this->panel->getCommandSearches(),
            ],
            'auth' => fn (): array => [
                'user' => $user === null ? null : [
                    'id' => $user->getAuthIdentifier(),
                    'name' => data_get($user, 'name'),
                    'email' => data_get($user, 'email'),
                ],
            ],
            'navigation' => fn (): array => $user instanceof Authorizable ? $this->panel->navigationFor($user) : [],
            'flash' => fn (): array => self::flash($request),
            ...$this->panel->getSharedData(),
        ];
    }

    /**
     * This request's messages, with an id that is new for each one shown
     * (the same text twice is two toasts), or null without messages.
     *
     * @return array{id: string|null, success: mixed, error: mixed}
     */
    private static function flash(Request $request): array
    {
        $success = $request->hasSession() ? $request->session()->get('success') : null;
        $error = $request->hasSession() ? $request->session()->get('error') : null;
        if ($success === null && $error === null) {
            return ['id' => null, 'success' => null, 'error' => null];
        }
        // Once per request, however often the closure runs.
        $id = $request->attributes->get('sunrice.flash_id');
        if (! is_string($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set('sunrice.flash_id', $id);
        }

        return ['id' => $id, 'success' => $success, 'error' => $error];
    }
}
