<?php

declare(strict_types=1);

namespace Sunrice\Admin\Http\Middleware;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Sunrice\Admin\Assets\AdminAssets;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Panel;

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
                'brand' => $this->panel->getBrand(),
                'path' => '/'.$this->panel->getPath(),
                'bundles' => array_values(array_map(fn (PluginBundle $bundle) => $bundle->toArray(), $this->panel->getBundles())),
            ],
            'auth' => fn (): array => [
                'user' => $user === null ? null : [
                    'id' => $user->getAuthIdentifier(),
                    'name' => data_get($user, 'name'),
                    'email' => data_get($user, 'email'),
                ],
            ],
            'navigation' => fn (): array => $user instanceof Authorizable ? $this->panel->navigationFor($user) : [],
            'flash' => fn (): array => [
                'success' => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => $request->hasSession() ? $request->session()->get('error') : null,
            ],
            ...$this->panel->getSharedData(),
        ];
    }
}
