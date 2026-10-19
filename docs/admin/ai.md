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
