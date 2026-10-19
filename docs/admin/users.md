# Users and roles

The panel comes with two screens under **Administration**, built as [resources](resources.md):

- **Users**: the users of the panel's guard (`config/auth.php`), with name, email, a new password (hashed; leave it empty when editing to keep the current one) and roles.
- **Roles**: the roles of the panel's guard and their permissions, grouped as the panel and its plugins register them (see [Roles and permissions](permissions.md)).

Their permissions are `sunrice.users.view`, `.create`, `.update`, `.delete` and `sunrice.roles.view` … Give them to the roles that should manage people; super admins have them anyway.

## Who may manage whom

Nobody can give out more than they have. On top of the permissions above (`Sunrice\Admin\Users\PermissionRank`):

- Super admins manage every user and role.
- Anyone else may edit or delete a user only if that user's permissions (through roles or given directly) are all permissions they have too. Super admins are out of their reach.
- They may give out, edit or delete a role only if its permissions are all ones they have; only those roles and permissions are offered and accepted. The super admin role is out of their reach.
- Nobody can delete their own account or change their own roles.
- The super admin role can't be edited or deleted: it may do everything anyway.

Saving a user or a role leaves alone what the form doesn't offer: roles of other guards and permissions the panel doesn't register (your app's own) stay as they are.

`PermissionRank` is public, for your own screens:

```php
use Sunrice\Admin\Users\PermissionRank;

app(PermissionRank::class)->canManageUser($admin, $user);
app(PermissionRank::class)->canManageRole($admin, $role);
app(PermissionRank::class)->canGrantPermissions($admin, ['shop.orders.refund']);
```

## Changing the screens

Extend the resources and register yours in your panel provider, or leave either screen out with `null`:

```php
use Sunrice\Admin\Resources\Forms\{Form, Section, TextInput};
use Sunrice\Admin\Users\UserResource;

final class AdminUserResource extends UserResource
{
    public static function form(Form $form): Form
    {
        return $form->schema([
            ...parent::form(new Form)->getSchema(),
            Section::make('Profile')->schema([TextInput::make('phone')]),
        ]);
    }
}

$panel->userResource(AdminUserResource::class)->roleResource(null);
```

The permission names stay `sunrice.users.*` and `sunrice.roles.*` whichever class you use. Your user model needs Spatie's `HasRoles` trait (see [Roles and permissions](permissions.md)), and the users table the `name`, `email` and `password` columns the default form writes.

## What a user owns

A user's page shows their last login, whether the account is locked, their
AI access tokens and what they own: files they uploaded, and what plugins
count (the CMS: entries they wrote). Deleting a user hands what they own
to whoever deletes them, so nothing is left without an owner; the activity
log says what was handed over.

Plugins say what people own with `$panel->ownedContent()` (`Users\OwnedContent`, `@api`):

```php
$panel->ownedContent(OwnedContent::make('shop.orders')->label('orders', 'order')
    ->count(fn (Model $user) => Order::where('owner_id', $user->getKey())->count())
    ->handOver(fn (Model $from, Model $to) => Order::where('owner_id', $from->getKey())->update(['owner_id' => $to->getKey()])));
```

New users' passwords are typed twice (`Password::make()->confirmed()`).
