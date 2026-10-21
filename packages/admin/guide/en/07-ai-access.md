# AI access

AI assistants such as Claude, ChatGPT or Cursor can work in {panel} for you: list and edit content, fill in fields, upload images, check SEO. They connect with an **access token** you create, and work **as you**, with exactly your permissions and no more.

**AI access** is under Administration. You see it when your role includes *Connect AI agents*.

## Creating a token

1. Open **AI access** and click **New token**.
2. Give it a **Name** after the assistant or computer that uses it (*Claude on my laptop*), so you know which one to revoke later.
3. Tick **Read-only** if the assistant should only look, never change anything.
4. Choose when it **Expires**: in 30 days, 90 days (the usual choice), a year, or **Never**.
5. Click **Create token**.
6. **Copy** the token straight away: it's shown only once. Anyone who has it can work in the panel as you, so keep it as safe as a password.

The page then shows how to connect: a command for **Claude Code**, and the settings for **Claude Desktop, Cursor, VS Code … (JSON config)**. Paste the token where it says.

## What an assistant can do

- Only what you can do. If your role can't publish, neither can your assistant.
- With a read-only token, only look, even if you're an administrator.
- Everything it changes is recorded in the [activity log](guide:panel/activity-log) under your name, marked **AI agent** with the token's name.
- Good assistants ask before deleting or publishing; still, read what they plan to do before you agree.

## Revoking a token

The list shows each token, whether it's read-only, when it was **Last used** and when it **Expires**. Click **Revoke** next to a token to stop it at once; assistants using it stop working. Create a new one if you still need it.

Tokens also stop working when:

- they expire;
- your account is locked, or a site manager uses **Sign out everywhere** or **Revoke AI tokens** on it;
- your role no longer allows the panel or AI access.

> [!WARNING]
> If a token may have been copied by someone else, revoke it straight away. The panel also emails the people who receive security alerts when a new token is created and when a token is used from a new address.

> [!TECHNICAL]
> Assistants connect to the MCP endpoint (`/mcp`). Tokens can also be created on the server with `php artisan sunrice:ai-token`. See [AI access](docs:panel/ai).
