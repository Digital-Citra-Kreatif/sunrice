# AI access

AI agents such as Claude, ChatGPT or Cursor can work in the panel through its [MCP](https://modelcontextprotocol.io) endpoint, as the user who owns their access token, with that user's permissions.

## Connecting an agent

1. Give the user's role **Connect AI agents** (`sunrice.ai.connect`). Super admins have it.
2. **Administration → AI access**: create a token, name it after the agent or computer that uses it, and copy it (it's shown once). Tick **Read-only** for an agent that should only look, and pick when it expires.
3. Add the panel to the agent with the settings shown on the page:

   ```bash
   claude mcp add --transport http panel https://example.com/mcp --header "Authorization: Bearer sra_…"
   ```

Revoke a token on the same page. Administrators revoke all of a user's tokens on **Users** (row menu → Revoke AI tokens), following [who may manage whom](users.md#who-may-manage-whom).

## What an agent can do

Exactly what its user can, and no more: every tool checks the user's permissions. With a **read-only** token it can only view: it sees only read-only tools, and every permission but viewing is refused, for super admins too.

- Changes show in the [activity log](activity.md) under the user, with the source **AI agent** and the token's name.
- A locked account's tokens stop working; so do expired and revoked ones.
- Each token may make `ai.requests_per_minute` (120) requests a minute.
- Security events: `token created`, `token revoked`, `tokens revoked`, and `token used from new IP` (a copied token?); the first and last also send [alerts](security.md#email-alerts).

## The panel's tools

| Tool | |
| --- | --- |
| `get_panel_info` | Start here: who the agent is, whether its token is read-only, its permissions, and the resources and settings sections it can reach. |
| `list_records` | A resource's list, as its screen shows it: search, filters, sort, pages. |
| `get_record` | A record's fields (type, label, required, options) and values, and the actions it may run; without an id, the create form. Passwords and secrets are never shown. |
| `save_record` | Create a record, or change one: only the fields sent change. Validated exactly like the screen. |
| `delete_record` | Delete a record (agents are told to ask first). |
| `run_action` | Run a resource's row or bulk action on records. |
| `get_settings`, `update_settings` | Read a settings section (secrets show only whether they're set) and change some of its settings. |
| `list_media` | Files and folders of the media library, with ids for media fields. |
| `get_activity` | The activity log, including what the agent itself did. |

They check the same permissions, policies and [who may manage whom](users.md#who-may-manage-whom) as the screens, validate with the same rules (`SaveRecord`, `UpdateSettings`), and write the same activity log lines.

## Developer tools

Agents can also work on the app itself: read and change its Blade views (templates), run console commands and read the error log. **Changing a view or running a command runs code on your server**, so these tools are off until you turn them on, and only people you give their permissions may use them:

```dotenv
SUNRICE_AI_DEVELOPER_TOOLS=true
```

| Tool | Permission | |
| --- | --- | --- |
| `list_views`, `read_view` | `sunrice.views.view` | The views under `resources/views`, by name (`cms.article`), and the earlier versions of one. |
| `save_view`, `delete_view` | `sunrice.views.update` | Create, replace or delete a view. |
| `run_command` | `sunrice.commands.run` | Run an allowed console command, like `view:clear` or `queue:retry all`. |
| `read_log` | `sunrice.logs.view` | The newest entries of a log file in `storage/logs`, errors and worse by default. |

- **Permissions:** they're in the role editor under *Developer tools*. A default role's `*` never includes them, so `sunrice:roles` doesn't give them to administrators: name them, or give them by hand. Super admins have them. A read-only token gets only `list_views`, `read_view` and `read_log`.
- **Views:** names may only use lowercase letters, numbers, `-` and `_` between the dots, so an agent can't reach files outside the views folder. A view must compile before it's saved, and is live at once. The content it replaces (or deletes) is kept: the newest 20 versions of each view, under `storage/app/sunrice/view-versions`. `read_view` lists them and reads one, so the agent can undo a change by saving it again. A change made on the server is lost on your next deploy unless you put it in the app's code too.
- **Commands:** only those named in `ai.developer_tools.commands`, with patterns like `cache:*`. By default these are the cache, config, route, view and event `clear` and `cache` commands, `optimize`, `about`, `migrate:status`, `route:list`, `schedule:list`, `queue:failed` and `queue:retry`. Add others, such as `migrate` or your own, only if agents should run them. Commands can't ask questions (they run with `--no-interaction`), and only the last 20,000 characters of their output come back.
- **The log:** only the last megabyte of a file is read. Bearer and AI tokens, and values that follow `password`, `secret`, `token` or `api_key`, are hidden. Logs can still hold personal data, so give `sunrice.logs.view` with care.
- Every view saved or deleted and every command run is in the [activity log](activity.md), with the agent's token.
- Config, under `ai.developer_tools`: `enabled`, `views_path` (`resource_path('views')`), `logs_path` (`storage_path('logs')`) and `commands`.

## Tools from plugins and apps

A tool is a class extending `Sunrice\Admin\Ai\Tool` (a [laravel/mcp](https://github.com/laravel/mcp) tool):

```php
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Sunrice\Admin\Ai\Tool;

#[IsReadOnly]
#[Description('List the shop\'s latest orders.')]
final class ListOrders extends Tool
{
    protected string $name = 'shop_list_orders';

    public function handle(Request $request): Response
    {
        if (! $this->can('shop.orders.view')) {
            return $this->denied('view orders');
        }

        return $this->json(Order::query()->latest()->limit(20)->get(['id', 'number', 'total']));
    }
}
```

Register it, with any instructions agents need, in a plugin's `register()` or your panel provider:

```php
$panel->tools(ListOrders::class, RefundOrder::class)
      ->aiInstructions('Orders: list them with shop_list_orders; refund with shop_refund_order (ask first).');
```

- A plugin's tool names start with its id: `shop_…`.
- Mark tools that only read with `#[IsReadOnly]`; read-only tokens get only those.
- Check permissions in every tool (`$this->can(...)`); use your actions, so validation and the activity log work as in the panel.

## Settings

`config/sunrice-admin.php` → `ai`: `enabled`, `path` (`mcp`) and `requests_per_minute`. `$panel->aiAccess(false)` removes the page, the permission and the endpoint.

## From the command line

`sunrice:ai-token {email}` creates a token for a user (to set up an agent
on a server), shown once with the endpoint: `--name`, `--read-only`,
`--days` (it expires). `--list` lists the user's tokens and `--revoke`
revokes them all.
