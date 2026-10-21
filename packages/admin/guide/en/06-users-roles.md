# Users and roles

Everyone who works in {panel} has a **user** account, and each account has one or more **roles**. A role is a set of permissions, such as *publish entries* or *upload media*, and decides what that person sees and may do. **Users** and **Roles** are under Administration, for site managers.

## Users

The **Users** list shows each person's name, email address, roles, whether the account is **Locked**, and when it was created. Search by name or email, or filter by role.

### Adding someone

1. Click **New user**.
2. Under **Account**, type their **Name** and **Email**. Then either type a **Password** and again in **Confirm password**, or leave it empty and turn on **Email an invitation**: they get an email with a link to choose their own password.
3. Under **Roles**, tick their roles.
4. Click **Create user**. Without an invitation, tell them the panel's address and their password.

To send someone a new link to choose their password (an invitation that ran out, say), use **Email a password link** in the row's menu.

The roles you can give are only those below your own (see [Who may manage whom](#who-may-manage-whom)).

### Editing someone

Click a user to change their name, email, password or roles. Leave **Password** empty to keep the current one; a new one is typed twice. Beside the form, the **Security** card shows whether the account is locked (when, by whom and why), when it was last signed in and from where, how many AI access tokens it has, what they own (**Owns**: their uploads, entries…), and its latest security events, with **See all in the activity log**.

Everyone changes their own name, email and password on **Your account** (under their name at the foot of the sidebar; see [Welcome](guide:panel/welcome#your-account)). Nobody can change their own roles or delete their own account.

## When an account may be in the wrong hands

If a password may have leaked, a laptop was lost, or someone left on bad terms, use the menu at the end of the user's row (or the Security card):

- **Sign out everywhere** ends all of their sessions, on every computer, and revokes their AI access tokens. They can sign in again with their password, so change it first if it may be known.
- **Lock account** signs them out everywhere and stops them signing in at all, until someone unlocks it. Add an optional reason, such as *Password may have leaked*; it's shown on their page. Nothing is deleted.
- **Unlock account** lets them sign in again. It also lifts a lock caused by too many wrong passwords.
- **Revoke AI tokens** stops every AI agent connected with their account.

Each of these is recorded as a security event in the [activity log](guide:panel/activity-log).

## Deleting a user

**Delete…** (in the row's menu, the bin button on their page, or for several ticked users at once) asks what happens to what they made (their entries, comments and uploads):

- **Give it to another user**, chosen in **Give it to** (the users you're deleting aren't offered).
- **Keep it, without an owner** (the default).
- **Delete it**: entries and comments go to the trash; uploads stay, since other pages may use them.

On a user's page, the question also says what they own. Their roles, AI tokens and lock state go with them. Prefer **Lock account** when you're not sure: it can be undone.

## Roles

The **Roles** list shows each role and how many permissions it has. Click **New role**, give it a **Name**, and tick its **Permissions**, grouped by area (the panel, media, entries of each collection, settings…).

Downloading a list as a spreadsheet (**Export CSV**) is a permission of its own, apart from seeing the list, since exports carry data out of the panel.

Your site may come with ready-made roles to start from:

| Role | For |
| --- | --- |
| **administrator** | Everything, for people who run the site. |
| **editor** | All content: entries, terms, globals, menus, comments, media, and reading and exporting form submissions. Not structure, settings or users. |
| **author** | Writing entries in every collection and editing their own, uploading media and seeing drafts on the site. They can't publish. To keep authors to some collections (only the *Journal*, say), untick *View entries* and *Create entries* and tick **View** and **Create** under those collections' *Entries:* groups. |
| **translator** | Writing the other languages of entries, in every collection they may view. To keep a translator to some collections, give the role **View** under those collections' *Entries:* groups instead of *View entries*. |

Every role that can open the panel can also read this guide.

Change the ready-made roles as you like. A role that is still given to someone can't be deleted: take it away from them first.

> [!NOTE]
> Some permissions give power over the whole site: managing users, roles and settings, connecting AI agents and the activity log (and, on the website, site code and blueprints). Whenever someone gets one of them, the people who receive security alerts get an email.

## Who may manage whom

Nobody can hand out more access than they have. You may manage a user or a role only when it is **below** you: everything it may do, you may do too, and you may do at least one thing more.

- You only see the roles below you, and only the permissions you have yourself.
- People with the same access as you, and super admins, are out of your reach.
- Super admins may do everything and manage everyone. The super admin role itself can't be edited or deleted.

> [!TECHNICAL]
> The first super admin is created on the server with `php artisan sunrice:make-admin`, and the ready-made roles with `php artisan sunrice:roles`. See [Roles and permissions](docs:panel/permissions) and [Users and roles](docs:panel/users).
