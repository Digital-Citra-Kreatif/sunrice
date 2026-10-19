<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use InvalidArgumentException;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Contracts\Plugin;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Permissions\Permission;

/**
 * The admin panel: where it lives, who can sign in, and everything the
 * app and its plugins add to it. Configured once, in the app's panel
 * provider (see PanelProvider), then locked when it boots.
 *
 *     Panel::make()
 *         ->path('admin')
 *         ->brand('Acme')
 *         ->plugin(CmsPlugin::make());
 *
 * @api
 */
final class Panel
{
    /** The permission prefix of the panel itself; no plugin may use it. */
    public const CORE = 'sunrice';

    /** The permission to open the panel at all. */
    public const ACCESS = 'sunrice.access';

    /** Page data keys the panel itself shares with every page. */
    private const RESERVED_SHARED = ['sunrice', 'auth', 'navigation', 'flash', 'errors'];

    /** Page folders of the panel's own React pages; plugin bundles use others. */
    private const RESERVED_PAGES = ['Auth/', 'Dashboard/', 'Users/', 'Roles/', 'Settings/', 'Media/', 'Activity/', 'Security/', 'Resources/'];

    private string $path = 'admin';

    private string $guard = 'web';

    private string $brand = 'Admin';

    /** @var list<string> */
    private array $middleware = [];

    /** @var array<string, Plugin> */
    private array $plugins = [];

    /** @var array<string, NavigationItem> */
    private array $navigation = [];

    /** @var array<string, Permission> */
    private array $permissions = [];

    /** @var array<string, Closure(): mixed> */
    private array $shared = [];

    /** @var list<Closure(): void> */
    private array $routes = [];

    /** @var array<string, PluginBundle> */
    private array $bundles = [];

    /** The plugin whose register() is running, if any: it owns what is declared meanwhile. */
    private ?Plugin $registering = null;

    private bool $booted = false;

    public static function make(): self
    {
        return new self;
    }

    // Configuration -------------------------------------------------------

    /** The URL path the panel lives under, e.g. "admin" or "backend/cms". */
    public function path(string $path): self
    {
        $this->assertConfigurable();
        $path = trim($path, '/');
        if (preg_match('#^[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*)*$#', $path) !== 1) {
            throw new InvalidArgumentException("Panel path \"{$path}\": use lowercase letters, numbers, dashes and slashes, like \"admin\".");
        }
        $this->path = $path;

        return $this;
    }

    /** The auth guard admins sign in with. */
    public function guard(string $guard): self
    {
        $this->assertConfigurable();
        $this->guard = $guard;

        return $this;
    }

    /** The name shown in the sidebar, the login page and emails. */
    public function brand(string $brand): self
    {
        $this->assertConfigurable();
        $this->brand = $brand;

        return $this;
    }

    /**
     * Extra middleware for every panel route, after the panel's own.
     *
     * @param  list<string>  $middleware
     */
    public function middleware(array $middleware): self
    {
        $this->assertConfigurable();
        $this->middleware = [...$this->middleware, ...$middleware];

        return $this;
    }

    public function plugin(Plugin $plugin): self
    {
        $this->assertConfigurable();
        $id = $plugin->id();
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $id) !== 1) {
            throw new PanelMisconfigured('Plugin '.$plugin::class." has the id \"{$id}\": use lowercase letters, numbers and dashes.");
        }
        if ($id === self::CORE) {
            throw new PanelMisconfigured('Plugin '.$plugin::class.' may not use the id "'.self::CORE.'": it belongs to the panel.');
        }
        if (isset($this->plugins[$id])) {
            throw new PanelMisconfigured("Two plugins have the id \"{$id}\": ".$this->plugins[$id]::class.' and '.$plugin::class.'.');
        }
        $this->plugins[$id] = $plugin;

        return $this;
    }

    // Registries (called by plugins in register(), or by the app) --------

    public function navigation(NavigationItem ...$items): self
    {
        $this->assertRegistering();
        foreach ($items as $item) {
            if (isset($this->navigation[$item->key])) {
                throw new PanelMisconfigured("Two navigation items have the key \"{$item->key}\"{$this->owner()}.");
            }
            $this->navigation[$item->key] = $item;
        }

        return $this;
    }

    /**
     * Permissions roles can be given. A plugin's permissions start with its
     * id ("shop.orders.view"); the app's may use any other prefix.
     */
    public function permissions(Permission ...$permissions): self
    {
        $this->assertRegistering();
        foreach ($permissions as $permission) {
            $this->assertOwnsPrefix($permission);
            if (isset($this->permissions[$permission->name])) {
                throw new PanelMisconfigured("The permission \"{$permission->name}\" is registered twice{$this->owner()}.");
            }
            $this->permissions[$permission->name] = $permission;
        }

        return $this;
    }

    /**
     * Data every admin page receives, resolved lazily per request:
     * `$panel->share('shop', fn () => ['currency' => 'IDR'])`.
     *
     * @param  Closure(): mixed  $value
     */
    public function share(string $key, Closure $value): self
    {
        $this->assertRegistering();
        if (in_array($key, self::RESERVED_SHARED, true)) {
            throw new PanelMisconfigured("The shared page data key \"{$key}\" belongs to the panel{$this->owner()}.");
        }
        if (isset($this->shared[$key])) {
            throw new PanelMisconfigured("The shared page data \"{$key}\" is registered twice{$this->owner()}.");
        }
        $this->shared[$key] = $value;

        return $this;
    }

    /**
     * Routes for signed-in users who can open the panel, under the panel's
     * path, named "sunrice.admin.…": `$panel->routes(fn () => Route::get('shop/orders', ...)->name('shop.orders.index'))`.
     * Protect each route further with the permission or policy it needs.
     *
     * @param  Closure(): void  $routes
     */
    public function routes(Closure $routes): self
    {
        $this->assertRegistering();
        $this->routes[] = $routes;

        return $this;
    }

    /** A plugin's admin JavaScript, loaded when one of its pages opens. */
    public function bundle(PluginBundle $bundle): self
    {
        $this->assertRegistering();
        if (isset($this->bundles[$bundle->id])) {
            throw new PanelMisconfigured("Two bundles have the id \"{$bundle->id}\"{$this->owner()}.");
        }
        if ($bundle->getModule() === null) {
            throw new PanelMisconfigured("The bundle \"{$bundle->id}\" has no module: set one with ->module(url).");
        }
        foreach ($bundle->getPages() as $prefix) {
            foreach (self::RESERVED_PAGES as $reserved) {
                if (str_starts_with($prefix, $reserved) || str_starts_with($reserved, $prefix)) {
                    throw new PanelMisconfigured("The bundle \"{$bundle->id}\" claims the pages \"{$prefix}\", which belong to the panel.");
                }
            }
            foreach ($this->bundles as $other) {
                foreach ($other->getPages() as $taken) {
                    if (str_starts_with($prefix, $taken) || str_starts_with($taken, $prefix)) {
                        throw new PanelMisconfigured("The bundles \"{$other->id}\" and \"{$bundle->id}\" both claim the pages \"{$prefix}\".");
                    }
                }
            }
        }
        $this->bundles[$bundle->id] = $bundle;

        return $this;
    }

    // Boot ----------------------------------------------------------------

    /**
     * Register and boot the plugins, dependencies first, and check what
     * they declared. Called once by PanelProvider; the panel can't be
     * changed afterwards.
     */
    public function boot(): void
    {
        if ($this->booted) {
            throw new PanelMisconfigured('The panel has already booted.');
        }

        $this->permissions(Permission::make(self::ACCESS, 'Open the admin panel', 'Panel'));

        $ordered = $this->pluginsInDependencyOrder();
        foreach ($ordered as $plugin) {
            $this->registering = $plugin;
            try {
                $plugin->register($this);
            } finally {
                $this->registering = null;
            }
        }
        $this->validate();

        $this->booted = true;
        foreach ($ordered as $plugin) {
            $plugin->boot($this);
        }
    }

    // Reading ---------------------------------------------------------------

    public function getPath(): string
    {
        return $this->path;
    }

    public function getGuard(): string
    {
        return $this->guard;
    }

    public function getBrand(): string
    {
        return $this->brand;
    }

    /** @return list<string> */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function hasPlugin(string $id): bool
    {
        return isset($this->plugins[$id]);
    }

    public function getPlugin(string $id): Plugin
    {
        return $this->plugins[$id] ?? throw new InvalidArgumentException("The panel has no plugin \"{$id}\".");
    }

    /** @return array<string, Plugin> keyed by id */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /** @return array<string, Permission> keyed by name */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /** @return array<string, Closure(): mixed> */
    public function getSharedData(): array
    {
        return $this->shared;
    }

    /** @return list<Closure(): void> */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /** @return array<string, PluginBundle> keyed by id */
    public function getBundles(): array
    {
        return $this->bundles;
    }

    /**
     * The sidebar for this user: groups in order of their first item,
     * items by sort then label, submenus under their parent. Items the
     * user may not see are left out, with their submenus.
     *
     * @return list<array{group: string|null, items: list<array{key: string, label: string, url: string, icon: string, children: list<array{key: string, label: string, url: string, icon: string}>}>}>
     */
    public function navigationFor(Authorizable $user): array
    {
        $visible = array_filter($this->navigation, function (NavigationItem $item) use ($user): bool {
            $permission = $item->getPermission();

            return $permission === null || $user->can($permission);
        });
        uasort($visible, fn (NavigationItem $a, NavigationItem $b) => [$a->getSort(), $a->getLabel()] <=> [$b->getSort(), $b->getLabel()]);

        $children = [];
        foreach ($visible as $item) {
            if ($item->getParent() !== null) {
                $children[$item->getParent()][] = self::link($item);
            }
        }

        $groups = [];
        foreach ($visible as $item) {
            if ($item->getParent() !== null) {
                continue;
            }
            $group = $item->getGroup();
            $groups[$group ?? ''] ??= ['group' => $group, 'items' => []];
            $groups[$group ?? '']['items'][] = [...self::link($item), 'children' => $children[$item->key] ?? []];
        }

        return array_values($groups);
    }

    // Internals -------------------------------------------------------------

    /** @return array{key: string, label: string, url: string, icon: string} */
    private static function link(NavigationItem $item): array
    {
        return ['key' => $item->key, 'label' => $item->getLabel(), 'url' => $item->getUrl(), 'icon' => $item->getIcon()];
    }

    /**
     * Plugins sorted so each comes after the plugins it requires; the
     * order they were added is kept otherwise.
     *
     * @return list<Plugin>
     */
    private function pluginsInDependencyOrder(): array
    {
        $ordered = [];
        $state = [];
        foreach ($this->plugins as $plugin) {
            $this->visit($plugin, [], $ordered, $state);
        }

        return $ordered;
    }

    /**
     * Depth-first visit for pluginsInDependencyOrder().
     *
     * @param  list<string>  $path  the plugins requiring this one, for the error message
     * @param  list<Plugin>  $ordered
     * @param  array<string, 'visiting'|'done'>  $state
     */
    private function visit(Plugin $plugin, array $path, array &$ordered, array &$state): void
    {
        $id = $plugin->id();
        if (($state[$id] ?? null) === 'done') {
            return;
        }
        if (($state[$id] ?? null) === 'visiting') {
            throw new PanelMisconfigured('Plugins require each other in a circle: '.implode(' → ', [...$path, $id]).'.');
        }
        $state[$id] = 'visiting';
        foreach ($plugin->requires() as $required) {
            if (! isset($this->plugins[$required])) {
                throw new PanelMisconfigured("The plugin \"{$id}\" requires the plugin \"{$required}\": add it to the panel with ->plugin(...).");
            }
            $this->visit($this->plugins[$required], [...$path, $id], $ordered, $state);
        }
        $state[$id] = 'done';
        $ordered[] = $plugin;
    }

    private function validate(): void
    {
        foreach ($this->navigation as $item) {
            $parent = $item->getParent();
            if ($parent !== null && ! isset($this->navigation[$parent])) {
                throw new PanelMisconfigured("The navigation item \"{$item->key}\" is under \"{$parent}\", which doesn't exist.");
            }
            if ($parent !== null && $this->navigation[$parent]->getParent() !== null) {
                throw new PanelMisconfigured("The navigation item \"{$item->key}\" is nested two levels deep; submenus have one level.");
            }
        }
    }

    private function assertOwnsPrefix(Permission $permission): void
    {
        $prefix = $permission->prefix();
        $owner = $this->registering?->id();

        if ($owner !== null && $prefix !== $owner) {
            throw new PanelMisconfigured("The plugin \"{$owner}\" registered the permission \"{$permission->name}\": a plugin's permissions start with its id (\"{$owner}.…\").");
        }
        if ($owner === null && $prefix !== self::CORE && isset($this->plugins[$prefix])) {
            throw new PanelMisconfigured("The permission \"{$permission->name}\" uses the prefix of the plugin \"{$prefix}\".");
        }
    }

    private function assertConfigurable(): void
    {
        if ($this->booted) {
            throw new PanelMisconfigured('The panel has booted and can no longer be changed: configure it in your panel provider.');
        }
    }

    private function assertRegistering(): void
    {
        if ($this->booted) {
            throw new PanelMisconfigured('The panel has booted: add navigation, permissions and shared data in a plugin\'s register() or your panel provider.');
        }
    }

    private function owner(): string
    {
        return $this->registering === null ? '' : " (by the plugin \"{$this->registering->id()}\")";
    }
}
