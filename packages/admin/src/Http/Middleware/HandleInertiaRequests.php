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
use Sunrice\Admin\Support\PanelLocale;
use Sunrice\Admin\Support\Say;

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
        return AdminAssets::inertiaVersion($this->panel);
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
                // What rich text links can point at (Panel::richTextLinkSource()): the editor's link menu searches them.
                'richTextLinks' => $user === null ? [] : array_values(array_map(fn ($source): array => ['key' => $source->key, 'label' => $source->label()], $this->panel->getRichTextLinkSources())),
                // The panel's language and those to pick from (the words themselves are in the page, PanelLocale::dictionary()).
                'locale' => app()->getLocale(),
                'locales' => PanelLocale::available(),
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
     * Every message of a field, not only its first: a password missing
     * three rules says all three at once ("Use both upper and lower case
     * letters. Add a symbol, such as ! or #."). Still one string per field.
     *
     * @return array<mixed>|object
     */
    public function resolveValidationErrors(Request $request): array|object
    {
        $this->withAllErrors = true;

        return self::joined(parent::resolveValidationErrors($request));
    }

    /**
     * A bag's (or bags') fields with their messages in one string each.
     *
     * @param  array<mixed>|object  $errors
     * @return array<mixed>|object
     */
    private static function joined(array|object $errors): array|object
    {
        $joined = [];
        foreach ((array) $errors as $key => $value) {
            $joined[$key] = match (true) {
                is_array($value) && array_is_list($value) => implode(' ', array_unique(array_filter($value, 'is_string'))),
                is_array($value) || is_object($value) => self::joined($value),
                default => $value,
            };
        }

        return is_object($errors) ? (object) $joined : $joined;
    }

    /**
     * This request's messages, with an id that is new for each one shown
     * (the same text twice is two toasts), or null without messages, and
     * the success toast's button ("success_action": Undo and the like).
     *
     * @return array{id: string|null, success: mixed, error: mixed, action: array{label: string, url: string, method: 'get'|'post', data: array<mixed>}|null}
     */
    private static function flash(Request $request): array
    {
        $success = $request->hasSession() ? $request->session()->get('success') : null;
        $error = $request->hasSession() ? $request->session()->get('error') : null;
        if ($success === null && $error === null) {
            return ['id' => null, 'success' => null, 'error' => null, 'action' => null];
        }
        $action = $request->session()->get('success_action');
        $action = $success !== null && is_array($action) && is_string($action['label'] ?? null) && is_string($action['url'] ?? null) ? [
            'label' => $action['label'],
            'url' => $action['url'],
            'method' => in_array($action['method'] ?? 'post', ['get', 'post'], true) ? (string) ($action['method'] ?? 'post') : 'post',
            'data' => is_array($action['data'] ?? null) ? $action['data'] : [],
        ] : null;
        // Once per request, however often the closure runs.
        $id = $request->attributes->get('sunrice.flash_id');
        if (! is_string($id)) {
            $id = (string) Str::uuid();
            $request->attributes->set('sunrice.flash_id', $id);
        }

        // A message given as an English sentence is said in the panel's language.
        $say = fn (mixed $message): mixed => is_string($message) ? __($message) : $message;
        if ($action !== null) {
            $action['label'] = Say::it($action['label']);
        }

        return ['id' => $id, 'success' => $say($success), 'error' => $say($error), 'action' => $action];
    }
}
