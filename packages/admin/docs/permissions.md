# Roles and permissions

The panel uses [spatie/laravel-permission](https://spatie.be/docs/laravel-permission). Its tables come with the panel's migrations (skipped if your app already has them), and your user model needs its trait:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

## Installing

```bash
php artisan sunrice:install --roles
```

It publishes `config/sunrice-admin.php` (when you don't have it yet), runs the migrations, publishes the admin's assets (and the plugins', tagged `laravel-assets`), links `public/storage` for the media library, syncs the permissions, creates the [default roles](#default-roles) with `--roles`, and offers to create a super admin (`--no-user` skips it). It's safe to run again. Your user model needs the `HasRoles` trait first; the command says so when it's missing.

## The first admin

```bash
php artisan migrate
php artisan sunrice:make-admin   # asks for name, email and password
```

`sunrice:make-admin` creates the user (or takes an existing one by `--email`) and gives them the super admin role. Pass `--name`, `--email` and `--password` to skip the questions; leave out `--password` to keep it out of your shell history.

## Super admins

Users with the `super-admin` role may do everything: a `Gate::before` check grants every ability, including your app's own. Change the role's name with `super_admin_role` in `config/sunrice-admin.php`.

## Permissions come from code

The panel and its plugins register their permissions in PHP (`$panel->permissions(Permission::make('shop.orders.view', 'View orders', 'Shop'))`, see [The panel and plugins](panel.md)). `php artisan sunrice:sync-permissions` makes the database match:

- it creates the permissions that are missing;
- it deletes the ones that are gone, **only** under the panel's prefix (`sunrice.`) and the plugins' prefixes: your app's other permissions are left alone;
- it creates the super admin role.

It also runs after every `php artisan migrate`, so a deploy keeps them in step.

A resource registers `<prefix>.view`, `.create`, `.update` and `.delete`, and `<prefix>.export` when its table is exportable ([Resources](resources.md#who-may-do-what)): viewing a list doesn't let anyone carry it out as CSV.

Every admin route needs `sunrice.access` ("Open the admin panel"); give it to the roles that may sign in to the panel. Unknown permissions are denied.

## Default roles

`php artisan sunrice:roles` (or `sunrice:install --roles`) creates roles to start from:

| Role | Permissions |
| --- | --- |
| `administrator` | Every permission, without the super admin's bypass of your app's own checks. |
| `editor` (the CMS) | The panel, media, and all content: entries, terms, globals, menus, comments, reading and exporting form submissions. Not structure, settings, users or roles. |
| `author` (the CMS) | The panel, viewing and uploading media, viewing and creating entries, editing and trashing their own, and seeing drafts on the site (`cms.entries.view-drafts`). Publishes nothing. |
| `translator` (the CMS) | The panel, viewing media and entries, and editing entries' other languages. |

Every default role with `sunrice.access` also gets `sunrice.guide.view`, the in-panel [guide](panel.md#help-pages). Run it again after adding plugins or collections: it gives these roles the permissions they're missing. It only adds, so permissions you give these roles in the panel stay, but one you take away comes back the next time. Plugins and your app add roles, or add to them, while the panel registers:

```php
$panel->defaultRole('cashier', ['sunrice.access', 'shop.orders.*']);   // names or patterns; "*" for every one
```

## Permissions from data

Some permissions depend on what's in the database, like the CMS's one per collection. Register them with a closure; the panel asks it when it needs them (the role editor, `sunrice:sync-permissions`):

```php
$panel->permissionsUsing(fn () => Shop::query()->get()->map(
    fn (Shop $shop) => Permission::make("shop.{$shop->handle}.orders", "Orders of {$shop->name}", 'Shops'))->all());
```

When the data changes, call `$panel->flushPermissions()` and `SyncPermissions::handle()` to create and delete them (the CMS does when collections, taxonomies and forms come and go).

When the data a permission is named after is **renamed**, rename the stored permissions instead, before anything syncs, so roles and people keep them:

```php
use Sunrice\Admin\Actions\Permissions\RenamePermissions;

app(RenamePermissions::class)->handle(['shop.north.orders' => 'shop.northwest.orders']);   // old => new; returns the old names renamed
$panel->flushPermissions();
```

A permission whose new name is already stored is left alone. The CMS does this when a collection's, taxonomy's or form's handle changes.

## Managing roles in the panel

The Users and Roles screens give out roles and permissions, and nobody can give out more than they have: see [Users and roles](users.md).
