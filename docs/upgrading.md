# Upgrading

`sunrice/admin` and `sunrice/cms` are released together, with the same version number: update both at once.

## How to upgrade

1. Read this page from the version you have to the one you're going to, and `CHANGELOG.md` for everything else that changed.
2. Update the packages:

   ```bash
   composer update "sunrice/*"
   ```

3. Run the steps every update needs (see [Installation](../packages/admin/docs/installation.md#upgrading)):

   ```bash
   php artisan migrate               # also syncs the permissions
   php artisan sunrice:assets        # the panel's built assets
   php artisan vendor:publish --tag=laravel-assets --force   # the CMS's and other plugins' (composer usually runs it)
   php artisan view:clear
   php artisan queue:restart
   ```

4. Optionally, `php artisan sunrice:roles` gives the [default roles](../packages/admin/docs/permissions.md#default-roles) the permissions new features added.
5. If you build plugin bundles with `@sunrice/admin-sdk`, update it to the version matching the panel and rebuild them.

Until 1.0, APIs may change between versions; every change is recorded in `CHANGELOG.md`, and from the first release on, the ones that need something from you are collected here, by version.
