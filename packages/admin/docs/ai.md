# AI access

AI agents such as Claude, ChatGPT or Cursor can work in the panel through its [MCP](https://modelcontextprotocol.io) endpoint, as the user who owns their access token, with that user's permissions.

## Connecting an agent

1. Give the user's role **Connect AI agents** (`sunrice.ai.connect`). Super admins have it.
2. **Administration → AI access**: create a token, name it after the agent or computer that uses it, and copy it (it's shown once). Tick **Read-only** for an agent that should only look, and pick when it expires (90 days unless you choose otherwise; **Never** is a choice, not the default).
3. Add the panel to the agent with the settings shown on the page:

   ```bash
   claude mcp add --transport http panel https://example.com/mcp --header "Authorization: Bearer sra_…"
   ```

Or from the console: `php artisan sunrice:ai-token rina@example.com --name="Claude Code" --read-only --expires=30` (`--expires=never`; 90 days by default) prints a new token once; `--list` lists the user's tokens and `--revoke=ID` revokes one. Both are `token created` / `token revoked` security events.

Revoke a token on the same page. Administrators revoke all of a user's tokens on **Users** (row menu → Revoke AI tokens), following [who may manage whom](users.md#who-may-manage-whom).

## What an agent can do

Exactly what its user can, and no more: every tool checks the user's permissions. With a **read-only** token it can only view: it sees only read-only tools, and every ability but viewing is refused, for super admins too. Only `view`, `viewAny`, permissions ending in `.view`, `sunrice.access`, `sunrice.ai.connect` and `sunrice.ai.error-log` pass (`TokenScope::READING`); anything else, including a policy ability the panel doesn't know (`publish`, `refund`), is refused.

- Changes show in the [activity log](activity.md) under the user, with the source **AI agent** and the token's name.
- A token works only while its owner may open the panel (`sunrice.access`) and connect agents, and isn't locked. **Sign out everywhere** and **Lock account** on Users [revoke the account's tokens](security.md#when-an-account-may-be-in-the-wrong-hands) (`tokens revoked`), so agents stop with the sessions; expired and revoked tokens stop too.
- Each token may make `ai.requests_per_minute` (120) requests a minute.
- Security events: `token created`, `token revoked`, `tokens revoked`, and `token used from new IP` (a copied token?); the first and last also send [alerts](security.md#email-alerts).

### An agent on the server itself

An agent running on the same machine (Claude Code in the project, say) can talk to the panel over stdio instead of HTTP:

```bash
claude mcp add panel -- php artisan mcp:start sunrice
```

It works as the user whose email is in `sunrice-admin.ai.local_user` (`SUNRICE_AI_USER`), with the same checks as a token's user (`sunrice.access`, `sunrice.ai.connect`, not locked); without one, `mcp:start` refuses to start. Its changes show in the activity log as **AI agent**, via `local (mcp:start)`.

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
| `upload_media` | Add a file from a public URL or base64 content, with a title, alt text and folder. Every check of an upload in the panel (`UploadMedia`: size, allowed types, no scripts, safe SVGs); URLs on this server or its private network are refused, and redirects aren't followed. Needs `sunrice.media.upload`. |
| `update_media` | Change a file's title, alt text or caption (`sunrice.media.update`). |
| `manage_media` | Folders (list, create, rename, delete), move files, trash and restore (`sunrice.media.delete`), where a file is used, and delete a trashed file for good: refused while content uses it unless `force` (agents are told to ask). |
| `get_activity` | The activity log, including what the agent itself did. |
| `read_docs` | The developer docs: the panel's (its package's `docs` folder), and those plugins register with `$panel->docs()` (the CMS: its `docs` folder). Pages are `section/page` (`panel/resources`); nothing outside those directories can be read. |
| `read_error_log` | Recent errors from the Laravel log (the last 2 MB of the three newest `*.log` files), grouped with a count and first and last time, the exception, file and line, the top of the trace, and passwords, tokens and keys masked. Needs `sunrice.ai.error-log` ("Let AI agents read the error log"); config `ai.error_log` turns it off, `ai.log_path` points elsewhere. |
| `run_command` | One of the artisan commands in `ai.commands`, with options checked against the command (and the list, when given); the output comes back, and an activity line records the command and its option names. Needs `sunrice.ai.run-commands`. |

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

## Docs for agents

`read_docs` reads Markdown files a plugin or the app registers, in its `register()` or the panel provider:

```php
$panel->docs('Shop', dirname(__DIR__).'/docs');   // pages "shop/orders", "shop/refunds" …
```

The section's key is the title's slug; a page's title is its first heading. A directory that doesn't exist is skipped. The same pages are on the panel's **Help → Developer docs** page (see [Help pages](panel.md#help-pages)).

## Settings

`config/sunrice-admin.php` → `ai`: `enabled`, `path` (`mcp`), `requests_per_minute`, `local_user` (the stdio agent's user, by email), `error_log` (`true`) and `log_path` (`storage/logs`) for `read_error_log`, and `commands`, the allow-list for `run_command`: a name takes any of the command's own options, `name => ['--days']` only those:

```php
'commands' => [
    'cache:clear',
    'sunrice:activity-prune' => ['--days'],
    'sunrice:publish-scheduled',      // the CMS's
],
```

`sunrice.ai.error-log` and `sunrice.ai.run-commands` are privileged permissions (an email alert when someone gets them), like `sunrice.ai.connect`. `$panel->aiAccess(false)` removes the page, the permission and the endpoint.
