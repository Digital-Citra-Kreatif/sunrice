# Users and roles

The panel comes with two screens under **Administration**, built as [resources](resources.md):

- **Users**: the users of the panel's guard (`config/auth.php`), with name, email, a new password typed twice (hashed; leave it empty when editing to keep the current one) and roles.
- **Roles**: the roles of the panel's guard and their permissions, grouped as the panel and its plugins register them (see [Roles and permissions](permissions.md)).

Their permissions are `sunrice.users.view`, `.create`, `.update`, `.delete` and `sunrice.roles.view` … Give them to the roles that should manage people; super admins have them anyway.

## Who may manage whom

Nobody can give out as much as they have, let alone more. Roles have no levels: a user or a role is **strictly below** someone when every permission it has, they have too, and they have at least one more. Side-by-side roles (an Editor and a Shop Manager, each with permissions the other lacks) are below neither. On top of the permissions above (`Sunrice\Admin\Users\PermissionRank`):

- Super admins manage every user and role.
- Anyone else may edit, delete or change the roles of a user only if that user is strictly below them (counting roles and permissions given directly). Super admins, and anyone with the same access as theirs, are out of their reach.
- They may give out, edit or delete a role only if it is strictly below them; only those roles, and permissions they have, are offered and accepted. A role they create or edit must end up strictly below them, and so must a user: roles that are each below them but together add up to their access are refused. The super admin role is out of their reach.
- Everyone may edit their own name, email and password. Nobody can delete their own account or change their own roles.
- The super admin role can't be edited or deleted: it may do everything anyway.
- A role still given to users can't be deleted: take it away from them first.

Saving a user or a role leaves alone what the form doesn't offer: roles of other guards and permissions the panel doesn't register (your app's own) stay as they are.

`PermissionRank` is public, for your own screens:

```php
use Sunrice\Admin\Users\PermissionRank;

app(PermissionRank::class)->canManageUser($admin, $user);          // strictly below $admin
app(PermissionRank::class)->canManageRole($admin, $role);          // strictly below $admin
app(PermissionRank::class)->canGrantPermissions($admin, ['shop.orders.refund']); // $admin has them all
app(PermissionRank::class)->isStrictlyBelow($admin, $permissions); // all theirs, and not all of theirs
```

## Deleting a user

What a user made shouldn't point at someone who is gone. **Delete…** on the Users screen (in the row's menu, on the edit page and for the selected users) asks what happens to it:

- **Give it to another user**: their content moves to the user you pick.
- **Keep it, without an owner** (the default): it stays, with no author or uploader.
- **Delete it**: each kind decides what that means. Uploads stay without an uploader, since other content may use them; the CMS moves their entries and comments to the trash.

The user's Security card says what they own ("Owns: 3 uploads and 12 entries"). Either way the user's AI access tokens, lock state and kept edits go with them, and roles are detached.

In code, `Sunrice\Admin\Actions\Users\DeleteUser` does the same (`REASSIGN`, `KEEP` and `DELETE`); it throws `ActionFailed` when reassigning without another user:

```php
app(DeleteUser::class)->counts($user);                            // ['entries' => 12, 'uploads' => 3]
app(DeleteUser::class)->handle($user, DeleteUser::REASSIGN, $heir);
```

Plugins and apps say what users own with `$panel->userContent()` (see [The panel and plugins](panel.md#what-users-own)). The panel registers uploads (`uploaded_by`); the CMS registers entries and comments.

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
