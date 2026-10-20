<?php

declare(strict_types=1);

namespace Sunrice\Admin;

use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Sunrice\Admin\Ai\Tool;
use Sunrice\Admin\Assets\PluginBundle;
use Sunrice\Admin\Contracts\Plugin;
use Sunrice\Admin\Dashboard\PanelWidgets;
use Sunrice\Admin\Dashboard\Widget;
use Sunrice\Admin\Exceptions\PanelMisconfigured;
use Sunrice\Admin\Media\Media;
use Sunrice\Admin\Navigation\NavigationItem;
use Sunrice\Admin\Permissions\Permission;
use Sunrice\Admin\Resources\RegisteredResource;
use Sunrice\Admin\Resources\Resource;
use Sunrice\Admin\Security\SecuritySettings;
use Sunrice\Admin\Settings\BrandingSettings;
use Sunrice\Admin\Settings\MailSettings;
use Sunrice\Admin\Settings\RegisteredSettings;
use Sunrice\Admin\Settings\Settings;
use Sunrice\Admin\Users\RoleResource;
use Sunrice\Admin\Users\UserContent;
use Sunrice\Admin\Users\UserResource;

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
    private const RESERVED_PAGES = ['Auth/', 'Dashboard/', 'Users/', 'Roles/', 'Settings/', 'Media/', 'Activity/', 'Security/', 'Resources/', 'Error'];

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

    /** @var array<string, Widget> keyed by the widget's key */
    private array $widgets = [];

    /** @var array<string, list<string>> role name => permission names or patterns */
    private array $defaultRoles = ['administrator' => ['*']];

    /** @var list<array{owner: string|null, resolve: Closure(): iterable<Permission>}> */
    private array $permissionResolvers = [];

    /** @var array<string, Permission>|null the resolvers' permissions, once asked */
    private ?array $resolvedPermissions = null;

    /** @var array<string, Closure(): mixed> */
    private array $shared = [];

    /** @var list<Closure(): void> */
    private array $routes = [];

    /** @var array<string, PluginBundle> */
    private array $bundles = [];

    /** @var array<string, RegisteredResource> keyed by the resource's key */
    private array $resources = [];

    /** @var array<string, RegisteredSettings> keyed by the section's key */
    private array $settings = [];

    private bool $mediaLibrary = true;

    private bool $aiAccess = true;

    /** @var list<class-string<Tool>> */
    private array $tools = [];

    /** @var list<string> */
    private array $aiInstructions = [];

    /** @var array<string, array{title: string, directory: string}> developer docs for AI agents, keyed by the title's slug */
    private array $docs = [];

    /** @var array<string, Closure(string, string, Authorizable): bool> */
    private array $lockables = [];

    /** @var array<string, UserContent> keyed by its key */
    private array $userContent = [];

    /** @var list<string> permission names or patterns ("sunrice.users.*") that give power worth alerting about */
    private array $privilegedPermissions = [];

    /** @var class-string<UserResource>|null */
    private ?string $userResource = UserResource::class;

    /** @var class-string<RoleResource>|null */
    private ?string $roleResource = RoleResource::class;

    /** URL segments the panel uses itself; resources can't take them. */
    private const RESERVED_KEYS = ['login', 'logout', 'forgot-password', 'reset-password', 'assets', 'settings', 'media', 'activity', 'security', 'profile', 'ai'];

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

    /**
     * The Users screen: the panel's own, yours (a subclass), or null for
     * none. Its permissions are "sunrice.users.view" … either way.
     *
     * @param  string|null  $resource  a UserResource class
     */
    public function userResource(?string $resource): self
    {
        $this->assertConfigurable();
        if ($resource !== null && ! is_a($resource, UserResource::class, true)) {
            throw new PanelMisconfigured("{$resource} isn't a user resource: extend ".UserResource::class.'.');
        }
        $this->userResource = $resource;

        return $this;
    }

    /**
     * The Roles screen: the panel's own, yours (a subclass), or null for
     * none. Its permissions are "sunrice.roles.view" … either way.
     *
     * @param  string|null  $resource  a RoleResource class
     */
    public function roleResource(?string $resource): self
    {
        $this->assertConfigurable();
        if ($resource !== null && ! is_a($resource, RoleResource::class, true)) {
            throw new PanelMisconfigured("{$resource} isn't a role resource: extend ".RoleResource::class.'.');
        }
        $this->roleResource = $resource;

        return $this;
    }

    /**
     * The Media page (on by default), with its permissions
     * "sunrice.media.view", ".upload", ".update" and ".delete". Without it,
     * the media library still works from code.
     */
    public function mediaLibrary(bool $enabled = true): self
    {
        $this->assertConfigurable();
        $this->mediaLibrary = $enabled;

        return $this;
    }

    /**
     * AI access (on by default): the AI access page, where users with
     * "sunrice.ai.connect" create tokens, and the MCP endpoint
     * (`sunrice-admin.ai.path`) AI agents connect to.
     */
    public function aiAccess(bool $enabled = true): self
    {
        $this->assertConfigurable();
        $this->aiAccess = $enabled;

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
     * Tiles on the dashboard (see Sunrice\Admin\Dashboard\Widget). A
     * plugin's keys start with its id and a dot ("shop.orders"); the app's
     * may not use a plugin's id.
     */
    public function widgets(Widget ...$widgets): self
    {
        $this->assertRegistering();
        $owner = $this->registering?->id();
        foreach ($widgets as $widget) {
            $prefix = explode('.', $widget->key)[0];
            if ($owner !== null && ! str_starts_with($widget->key, $owner.'.')) {
                throw new PanelMisconfigured("The plugin \"{$owner}\" registered the widget \"{$widget->key}\": a plugin's widget keys start with its id (\"{$owner}.…\").");
            }
            if ($owner === null && isset($this->plugins[$prefix])) {
                throw new PanelMisconfigured("The widget \"{$widget->key}\" uses the prefix of the plugin \"{$prefix}\".");
            }
            if (isset($this->widgets[$widget->key])) {
                throw new PanelMisconfigured("Two widgets have the key \"{$widget->key}\"{$this->owner()}.");
            }
            if (! $widget->hasData()) {
                throw new PanelMisconfigured("The widget \"{$widget->key}\" has no data: give it ->data(fn (Authorizable \$user) => [...]).");
            }
            $this->widgets[$widget->key] = $widget->inGroup($owner === null ? 'widgets' : "widgets-{$owner}");
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
     * Permissions that depend on data, like one per collection of a CMS,
     * resolved when they're needed (by the role editor, permission syncs)
     * and again after flushPermissions():
     *
     *     $panel->permissionsUsing(fn () => Shop::query()->get()->map(
     *         fn ($shop) => Permission::make("shop.{$shop->handle}.orders", "Orders of {$shop->name}", 'Shops')));
     *
     * Their names start with the plugin's id, like its other permissions.
     * Run SyncPermissions after the data changes to create and delete them.
     *
     * @param  Closure(): iterable<Permission>  $resolve
     */
    public function permissionsUsing(Closure $resolve): self
    {
        $this->assertRegistering();
        $this->permissionResolvers[] = ['owner' => $this->registering?->id(), 'resolve' => $resolve];

        return $this;
    }

    /** Forget the permissions permissionsUsing() resolved, after their data changed. */
    public function flushPermissions(): void
    {
        $this->resolvedPermissions = null;
    }

    /**
     * A role `sunrice:roles` creates (or adds permissions to) with these
     * permissions: names, or patterns with `*` ("cms.entries.*"; "*" for
     * every one). Plugins add to the same role by naming it again:
     *
     *     $panel->defaultRole('editor', ['sunrice.access', 'shop.orders.*']);
     *
     * The panel has "administrator" (every permission, without the super
     * admin's bypass).
     *
     * @param  list<string>  $permissions
     */
    public function defaultRole(string $name, array $permissions): self
    {
        $this->assertRegistering();
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name) !== 1) {
            throw new PanelMisconfigured("Role \"{$name}\": use a lowercase name like \"editor\".");
        }
        $this->defaultRoles[$name] = array_values(array_unique([...($this->defaultRoles[$name] ?? []), ...$permissions]));

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

    /**
     * Admin screens for models (see Resource). Each gets a sidebar item,
     * routes under the panel ("/admin/products") and four permissions,
     * prefixed with the registering plugin's id ("shop.products.view") or,
     * when the app registers it, the resource's key ("products.view").
     *
     * @param  class-string<Resources\Resource>  ...$classes
     */
    public function resources(string ...$classes): self
    {
        $this->assertRegistering();
        foreach ($classes as $class) {
            if (! is_subclass_of($class, Resource::class)) {
                throw new PanelMisconfigured("{$class} isn't a resource: extend ".Resource::class.'.');
            }
            $this->registerResource($class, $this->registering === null ? $class::key() : $this->registering->id().'.'.$class::key());
        }

        return $this;
    }

    /**
     * Settings sections (see Settings), edited on the panel's Settings page.
     * Each gets a permission to edit it, prefixed like resources:
     * "shop.settings.payments" from a plugin, "settings.general" from the app.
     *
     * @param  class-string<Settings>  ...$classes
     */
    public function settings(string ...$classes): self
    {
        $this->assertRegistering();
        foreach ($classes as $class) {
            if (! is_subclass_of($class, Settings::class)) {
                throw new PanelMisconfigured("{$class} isn't a settings section: extend ".Settings::class.'.');
            }
            $key = $class::key();
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1) {
                throw new PanelMisconfigured("The settings section {$class} has the key \"{$key}\": use lowercase letters, numbers and dashes.");
            }
            if (isset($this->settings[$key])) {
                throw new PanelMisconfigured("Two settings sections have the key \"{$key}\": {$this->settings[$key]->class} and {$class}{$this->owner()}.");
            }

            $permission = ($this->registering === null ? '' : $this->registering->id().'.')."settings.{$key}";
            $this->permissions(Permission::make($permission, "Edit {$class::label()} settings", 'Settings'));
            $this->settings[$key] = new RegisteredSettings($class, $permission);
        }

        return $this;
    }

    /**
     * Tools for AI agents (see Sunrice\Admin\Ai\Tool). A plugin's tool names
     * start with its id and an underscore ("shop_list_orders").
     *
     * @param  class-string<Tool>  ...$tools
     */
    public function tools(string ...$tools): self
    {
        $this->assertRegistering();
        foreach ($tools as $tool) {
            if (! is_subclass_of($tool, Tool::class)) {
                throw new PanelMisconfigured("{$tool} isn't a tool for AI agents: extend ".Tool::class.'.');
            }
            $name = (new \ReflectionClass($tool))->newInstanceWithoutConstructor()->name();
            $owner = $this->registering?->id();
            if ($owner !== null && ! str_starts_with($name, str_replace('-', '_', $owner).'_')) {
                throw new PanelMisconfigured("The plugin \"{$owner}\" registered the tool \"{$name}\": a plugin's tool names start with its id (\"".str_replace('-', '_', $owner).'_…").');
            }
            if (in_array($tool, $this->tools, true)) {
                throw new PanelMisconfigured("The tool {$tool} is registered twice{$this->owner()}.");
            }
            $this->tools[] = $tool;
        }

        return $this;
    }

    /**
     * Something editors open that should have an edit lock (see
     * Sunrice\Admin\Locks\EditLocks): a type name, and who may edit an
     * item of it, by id and language ('' when it has none). A plugin's
     * types start with its id and a dot ("shop.product").
     *
     *     $panel->lockable('shop.product', fn (string $id, string $locale, Authorizable $user) => $user->can('shop.products.update'));
     *
     * @param  Closure(string, string, Authorizable): bool  $mayEdit
     */
    public function lockable(string $type, Closure $mayEdit): self
    {
        $this->assertRegistering();
        if (preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', $type) !== 1) {
            throw new PanelMisconfigured("The lockable type \"{$type}\": use lowercase letters, numbers, dots and dashes.");
        }
        $owner = $this->registering?->id();
        if ($owner !== null && ! str_starts_with($type, $owner.'.')) {
            throw new PanelMisconfigured("The plugin \"{$owner}\" registered the lockable type \"{$type}\": a plugin's types start with its id (\"{$owner}.…\").");
        }
        if (isset($this->lockables[$type])) {
            throw new PanelMisconfigured("The lockable type \"{$type}\" is registered twice{$this->owner()}.");
        }
        $this->lockables[$type] = $mayEdit;

        return $this;
    }

    /**
     * Something users own, so deleting a user (Sunrice\Admin\Actions\Users\DeleteUser)
     * can give it to someone else, keep it without an owner or delete it:
     * a key (a plugin's start with its id), a label for the dialog
     * ("orders"), how to count a user's, and what to do with it. `$mode`
     * is "reassign" (with `$heir`), "keep" or "delete".
     *
     *     $panel->userContent('shop.orders', 'orders',
     *         fn (Model $user): int => Order::query()->where('user_id', $user->getKey())->count(),
     *         fn (Model $user, string $mode, ?Model $heir) => Order::query()->where('user_id', $user->getKey())
     *             ->update(['user_id' => $heir?->getKey()]));
     *
     * @param  Closure(Model): int  $count
     * @param  Closure(Model, string, ?Model): void  $handle
     */
    public function userContent(string $key, string $label, Closure $count, Closure $handle): self
    {
        $this->assertRegistering();
        if (preg_match('/^[a-z0-9]+(?:[.-][a-z0-9]+)*$/', $key) !== 1) {
            throw new PanelMisconfigured("The user content key \"{$key}\": use lowercase letters, numbers, dots and dashes.");
        }
        $owner = $this->registering?->id();
        if ($owner !== null && ! str_starts_with($key, $owner.'.')) {
            throw new PanelMisconfigured("The plugin \"{$owner}\" registered the user content \"{$key}\": a plugin's keys start with its id (\"{$owner}.…\").");
        }
        if (isset($this->userContent[$key])) {
            throw new PanelMisconfigured("The user content \"{$key}\" is registered twice{$this->owner()}.");
        }
        $this->userContent[$key] = new UserContent($key, $label, $count, $handle);

        return $this;
    }

    /**
     * Permissions that give power worth an email alert when someone gets
     * them (see Sunrice\Admin\Security\SecurityAlerts): names, or patterns
     * with `*`. The panel's own are managing users, roles and settings,
     * connecting AI agents and reading the activity log. A plugin's start
     * with its id.
     *
     *     $panel->privilegedPermissions('shop.settings.*', 'shop.payouts.manage');
     */
    public function privilegedPermissions(string ...$names): self
    {
        $this->assertRegistering();
        $owner = $this->registering?->id();
        foreach ($names as $name) {
            if ($owner !== null && ! str_starts_with($name, $owner.'.')) {
                throw new PanelMisconfigured("The plugin \"{$owner}\" marked the permission \"{$name}\" as privileged: a plugin's permissions start with its id (\"{$owner}.…\").");
            }
            if (! in_array($name, $this->privilegedPermissions, true)) {
                $this->privilegedPermissions[] = $name;
            }
        }

        return $this;
    }

    /** More instructions for AI agents: how to work with what you added. */
    public function aiInstructions(string $markdown): self
    {
        $this->assertRegistering();
        $this->aiInstructions[] = trim($markdown);

        return $this;
    }

    /**
     * Developer documentation AI agents may read with read_docs: the
     * Markdown files (`*.md`) of a directory, under a title. Pages are
     * named "{slug of the title}/{file name}" ("cms/collections"). The
     * panel registers its own as "Panel"; a missing directory is skipped.
     *
     *     $panel->docs('Shop', dirname(__DIR__).'/docs');
     */
    public function docs(string $title, string $directory): self
    {
        $this->assertRegistering();
        $key = Str::slug($title);
        if ($key === '') {
            throw new PanelMisconfigured("The docs \"{$title}\" need a title with letters or numbers.");
        }
        if (isset($this->docs[$key])) {
            throw new PanelMisconfigured("Two docs are called \"{$title}\"{$this->owner()}.");
        }
        $this->docs[$key] = ['title' => $title, 'directory' => rtrim($directory, '/')];

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

        // The panel's own permissions first, before the app's and the plugins'.
        $declared = $this->permissions;
        $this->permissions = [];
        $this->permissions(Permission::make(self::ACCESS, 'Open the admin panel', 'Panel'));
        $this->permissions(
            Permission::make('sunrice.activity.view', 'View the activity log', 'Panel'),
            Permission::make('sunrice.activity.prune', 'Delete old activity log lines', 'Panel'),
        );
        // Settings → Branding, Security (two-factor login) and Mail (how mail goes, a test email), in every panel.
        foreach ([BrandingSettings::class, SecuritySettings::class, MailSettings::class] as $own) {
            if (isset($this->settings[$own::key()])) {
                throw new PanelMisconfigured("The settings key \"{$own::key()}\" belongs to the panel: give {$this->settings[$own::key()]->class} another key.");
            }
        }
        $this->permissions(
            Permission::make('sunrice.settings.branding', 'Edit branding settings', 'Settings'),
            Permission::make('sunrice.settings.security', 'Edit security settings', 'Settings'),
            Permission::make('sunrice.settings.mail', 'See mail settings and send test emails', 'Settings'),
        );
        $this->settings = [
            BrandingSettings::key() => new RegisteredSettings(BrandingSettings::class, 'sunrice.settings.branding'),
            SecuritySettings::key() => new RegisteredSettings(SecuritySettings::class, 'sunrice.settings.security'),
            MailSettings::key() => new RegisteredSettings(MailSettings::class, 'sunrice.settings.mail'),
            ...$this->settings,
        ];
        $this->navigation(NavigationItem::make('activity')->label('Activity log')->url('activity')->icon('history')
            ->group('Administration')->sort(960)->permission('sunrice.activity.view'));
        $this->widgets(PanelWidgets::activity());
        if ($this->aiAccess) {
            $this->permissions(
                Permission::make('sunrice.ai.connect', 'Connect AI agents', 'AI access'),
                Permission::make('sunrice.ai.error-log', 'Let AI agents read the error log', 'AI access'),
                Permission::make('sunrice.ai.run-commands', 'Let AI agents run allowed commands', 'AI access'),
            );
            // The panel's developer docs (this repository's docs/admin), for read_docs.
            $this->docs('Panel', dirname(__DIR__, 3).'/docs/admin');
            $this->navigation(NavigationItem::make('ai')->label('AI access')->url('ai')->icon('bot')
                ->group('Administration')->sort(970)->permission('sunrice.ai.connect'));
        }
        if ($this->mediaLibrary) {
            $this->permissions(
                Permission::make('sunrice.media.view', 'View media', 'Media'),
                Permission::make('sunrice.media.upload', 'Upload media', 'Media'),
                Permission::make('sunrice.media.update', 'Edit and organise media', 'Media'),
                Permission::make('sunrice.media.delete', 'Delete media', 'Media'),
            );
            $this->navigation(NavigationItem::make('media')->label('Media')->url('media')->icon('image')->sort(50)->permission('sunrice.media.view'));
            $this->widgets(PanelWidgets::media());
            // Deleting a user: their uploads move to the heir or stay without an uploader (other content may use them).
            $this->userContent('media', 'uploads',
                fn (Model $user): int => Media::query()->where('uploaded_by', self::id($user))->count(),
                function (Model $user, string $mode, ?Model $heir): void {
                    Media::withTrashed()->where('uploaded_by', self::id($user))->update(['uploaded_by' => $heir === null ? null : self::id($heir)]);
                });
        }
        // Getting one of these is an email alert (SecurityAlerts); plugins add theirs.
        $this->privilegedPermissions('sunrice.users.*', 'sunrice.roles.*', 'sunrice.settings.*', 'sunrice.ai.*', 'sunrice.activity.*');
        if ($this->userResource !== null) {
            $this->registerResource($this->userResource, self::CORE.'.users');
        }
        if ($this->roleResource !== null) {
            $this->registerResource($this->roleResource, self::CORE.'.roles');
        }
        foreach ($declared as $name => $permission) {
            if (isset($this->permissions[$name])) {
                throw new PanelMisconfigured("The permission \"{$name}\" is registered twice.");
            }
            $this->permissions[$name] = $permission;
        }

        $ordered = $this->pluginsInDependencyOrder();
        foreach ($ordered as $plugin) {
            $this->registering = $plugin;
            try {
                $plugin->register($this);
            } finally {
                $this->registering = null;
            }
        }
        if ($this->settings !== []) {
            $sections = $this->settings;
            $this->navigation(NavigationItem::make('settings')->label('Settings')->url('settings')->icon('settings')
                ->group('Administration')->sort(950)
                ->visible(function (Authorizable $user) use ($sections): bool {
                    foreach ($sections as $section) {
                        if ($section->allows($user)) {
                            return true;
                        }
                    }

                    return false;
                }));
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

    /**
     * The user model of the panel's guard (config/auth.php).
     *
     * @return class-string<Model>
     */
    public function getUserModel(): string
    {
        $provider = config("auth.guards.{$this->guard}.provider");
        $model = is_string($provider) ? config("auth.providers.{$provider}.model") : null;
        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new PanelMisconfigured("The panel's guard \"{$this->guard}\" has no Eloquent user model in config/auth.php.");
        }

        return $model;
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

    public function hasAiAccess(): bool
    {
        return $this->aiAccess;
    }

    /** @return list<class-string<Tool>> */
    public function getTools(): array
    {
        return $this->tools;
    }

    /** @return array<string, Closure(string, string, Authorizable): bool> */
    public function getLockables(): array
    {
        return $this->lockables;
    }

    /** @return array<string, UserContent> keyed by its key */
    public function getUserContent(): array
    {
        return $this->userContent;
    }

    /** @return list<string> names or patterns */
    public function getPrivilegedPermissions(): array
    {
        return $this->privilegedPermissions;
    }

    /** Whether getting this permission is worth an alert (see privilegedPermissions()). */
    public function isPrivilegedPermission(string $name): bool
    {
        foreach ($this->privilegedPermissions as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /** A user's key as it is stored in the panel's tables. */
    private static function id(Model $user): string
    {
        $key = $user->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /** @return array<string, array{title: string, directory: string}> keyed by the title's slug */
    public function getDocs(): array
    {
        return $this->docs;
    }

    /** @return list<string> */
    public function getAiInstructions(): array
    {
        return $this->aiInstructions;
    }

    public function hasMediaLibrary(): bool
    {
        return $this->mediaLibrary;
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
        if ($this->permissionResolvers === []) {
            return $this->permissions;
        }
        if ($this->resolvedPermissions === null) {
            $resolved = [];
            foreach ($this->permissionResolvers as ['owner' => $owner, 'resolve' => $resolve]) {
                foreach ($resolve() as $permission) {
                    if ($owner !== null && $permission->prefix() !== $owner) {
                        throw new PanelMisconfigured("The plugin \"{$owner}\" resolved the permission \"{$permission->name}\": a plugin's permissions start with its id (\"{$owner}.…\").");
                    }
                    if (! isset($this->permissions[$permission->name])) {
                        $resolved[$permission->name] = $permission;
                    }
                }
            }
            $this->resolvedPermissions = $resolved;
        }

        return [...$this->permissions, ...$this->resolvedPermissions];
    }

    /** @return array<string, list<string>> role name => permission names or patterns */
    public function getDefaultRoles(): array
    {
        return $this->defaultRoles;
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

    /** @return array<string, RegisteredResource> keyed by the resource's key */
    public function getResources(): array
    {
        return $this->resources;
    }

    /** @return array<string, RegisteredSettings> keyed by the section's key */
    public function getSettings(): array
    {
        return $this->settings;
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
        $visible = array_filter($this->navigation, fn (NavigationItem $item): bool => $item->isVisibleTo($user));
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

    /** @return array<string, Widget> keyed by key */
    public function getWidgets(): array
    {
        return $this->widgets;
    }

    /**
     * The dashboard's widgets this user may see, by sort, then in the
     * order they were registered. Their data isn't resolved.
     *
     * @return list<Widget>
     */
    public function widgetsFor(Authorizable $user): array
    {
        $visible = array_values(array_filter($this->widgets, fn (Widget $widget): bool => $widget->isVisibleTo($user)));
        usort($visible, fn (Widget $a, Widget $b): int => $a->getSort() <=> $b->getSort());

        return $visible;
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

    /** @param class-string<resource> $class */
    private function registerResource(string $class, string $prefix): void
    {
        $key = $class::key();
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1 || in_array($key, self::RESERVED_KEYS, true)) {
            throw new PanelMisconfigured("The resource {$class} has the key \"{$key}\": use lowercase letters, numbers and dashes, and not one of the panel's own paths.");
        }
        if (isset($this->resources[$key])) {
            throw new PanelMisconfigured("Two resources have the key \"{$key}\": {$this->resources[$key]->class} and {$class}{$this->owner()}.");
        }

        $resource = new RegisteredResource($class, $prefix);
        $this->resources[$key] = $resource;

        $label = $class::label();
        $this->permissions(
            Permission::make("{$prefix}.view", "View {$label}", $label),
            Permission::make("{$prefix}.create", "Create {$label}", $label),
            Permission::make("{$prefix}.update", "Edit {$label}", $label),
            Permission::make("{$prefix}.delete", "Delete {$label}", $label),
        );
        $this->navigation(
            NavigationItem::make("resources.{$key}")->label($label)->url($key)->icon($class::navigationIcon())
                ->group($class::navigationGroup())->sort($class::navigationSort())->visible($resource->visibleTo()),
        );
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
