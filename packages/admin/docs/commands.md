# Commands

The panel's Artisan commands. The CMS has its own: see [CMS commands](../../cms/docs/commands.md).

| Command | What it does |
| --- | --- |
| `sunrice:install [--roles] [--plugins] [--no-user]` | Installs the panel: writes `app/Providers/AdminPanelProvider.php` with the installed plugins and registers it when the app has no panel, offers to add plugins installed later that the panel doesn't have to its provider (`--plugins`: without asking; without interaction it prints what to add; [Installing a plugin later](installation.md#installing-a-plugin-later)), offers to add `HasRoles` to the user model, publishes `config/sunrice-admin.php` (when missing), runs the migrations, publishes the assets (the panel's and `laravel-assets`), links `public/storage`, writes an Apache `.htaccess` with headers into a local media directory that has none ([Media](media.md#headers-at-the-web-server)), syncs the permissions, creates the default roles with `--roles`, runs the plugins' install steps (`Panel::installStep()`, see [Plugins](plugins.md#the-install-step); the CMS's offers to remove Laravel's welcome route from `routes/web.php`), and offers to create a super admin unless `--no-user`. Safe to run again. See [Installation](installation.md). |
| `sunrice:make-admin [--name=] [--email=] [--password=]` | Creates a super admin, or makes an existing user (by `--email`) one. Leave out `--password` to be asked, so it stays out of your shell history. See [Roles and permissions](permissions.md#the-first-admin). |
| `sunrice:sync-permissions` | Creates the permissions the panel and its plugins register and deletes those gone (only under the panel's and plugins' prefixes), and creates the super admin role. Also runs after every `migrate`. |
| `sunrice:roles` | Creates the [default roles](permissions.md#default-roles), or gives them the permissions they're missing. Only adds. |
| `sunrice:two-factor [on\|off]` | Turns [two-factor login](auth.md#two-factor-login) on or off; without an argument, says which. For when email stopped working and nobody can log in. |
| `sunrice:unlock {email}` | Unlocks an account locked by an administrator or after too many wrong passwords. See [Security](security.md#when-an-account-may-be-in-the-wrong-hands). |
| `sunrice:ai-token {email} [--name=] [--read-only] [--expires=90\|never] [--list] [--revoke=ID]` | Creates an AI access token for a user (printed once), or lists or revokes theirs. See [AI access](ai.md#connecting-an-agent). |
| `mcp:start sunrice` | Serves the panel's AI tools over stdio for an agent on the same machine, as the user in `sunrice-admin.ai.local_user` (`SUNRICE_AI_USER`); refuses to start without one. See [AI access](ai.md#an-agent-on-the-server-itself). |
| `sunrice:media-sizes [--id=*]` | Makes the media library's image sizes again (after changing `media.image_sizes`), for every image or the ids given. |
| `sunrice:optimize-images [--id=*] [--max-width=] [--max-height=] [--quality=] [--no-backup] [--dry-run] [--restore]` | Scales down and compresses images in place, keeping the first originals; `--restore` puts them back. See [Media](media.md#optimizing-images). |
| `sunrice:assets` | Publishes the panel's built JavaScript and CSS to `public/vendor/sunrice-admin`. Run it after every update. See [Installation](installation.md#assets). |
| `sunrice:activity-prune [--days=]` | Deletes activity log lines older than `activity.keep_days` (or `--days`). Scheduled daily. See [Activity log](activity.md#keeping-it-small). |

## Scheduled

With Laravel's scheduler running (`* * * * * php artisan schedule:run`), the panel runs `sunrice:activity-prune` and `model:prune` for kept edits ([edit locks](locks.md)) daily. The CMS adds its own (see [CMS commands](../../cms/docs/commands.md)).

## Allowed for AI agents

Agents with `sunrice.ai.run-commands` may run the commands listed in `sunrice-admin.ai.commands` with the `run_command` tool, by default `sunrice:activity-prune`, `sunrice:sync-permissions`, `sunrice:media-sizes`, `sunrice:optimize-images` and the `cache:clear`, `view:clear`, `config:clear`, `route:clear` and `optimize:clear` commands. See [Configuration](configuration.md#ai-access).
