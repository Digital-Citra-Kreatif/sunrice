# Roles and permissions

The panel uses [spatie/laravel-permission](https://spatie.be/docs/laravel-permission). Its tables come with the panel's migrations (skipped if your app already has them), and your user model needs its trait:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasRoles;
}
```

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

Every admin route needs `sunrice.access` ("Open the admin panel"); give it to the roles that may sign in to the panel. Unknown permissions are denied.

## Managing roles in the panel

The Users and Roles screens give out roles and permissions, and nobody can give out more than they have: see [Users and roles](users.md).
