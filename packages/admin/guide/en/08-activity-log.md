# Activity log and security

The **Activity log** under Administration answers *who changed what, and when*. It's how you find out what happened to a page, follow up on a mistake, or spot something that shouldn't have happened.

## Reading the log

Each line shows **When**, **Who**, **What** (the action and the item) and **Details** (the fields that changed, and from which address). Newest lines come first.

- **Source** tells where a change came from: **Admin** (someone in the panel), **AI agent** (with the name of its token), **System** (the server itself, such as scheduled publishing) or **Security** (sign-ins and other security events).
- The log names the fields that changed, never their old or new values, so it holds no passwords or personal details.

## Finding something

- **Search…** looks through people's names, items and actions.
- Filter by **Source**, by kind of item, by **Action** (*published*, *deleted*, *logged in*…) or by dates.
- Click a person's name to see only their activity.
- Click the history icon on a line to see the whole history of that one item.
- **Lines per page** shows 25, 50, 100 or 200 lines.

The filters stay in the page's address, so you can bookmark a search or send it to a colleague.

## Security events

Lines with the source **Security** record what matters for spotting a break-in: signing in and out, wrong passwords, blocked sign-ins, locked accounts, password resets, blocked uploads, exports, changes to roles and permissions, two-factor login turned on or off, AI tokens created or revoked, and pages refused to someone.

Filter the log by the **Security** source to see only them. A user's own security events are also on their page under Users (the **Security** card).

## Security alerts by email

When something may be a break-in, the panel emails the site's super admins (and anyone else your developer chose). For example:

- an account was locked after many wrong passwords, or one address is trying many accounts;
- someone with a lot of power signed in from a new address, or after a long time;
- someone or a role was given powerful permissions;
- a dangerous upload was blocked;
- an AI token was created, or used from a new address;
- two-factor login was turned off, or the website's site code changed;
- the activity log was pruned;
- someone keeps opening pages they may not see, or deletes or exports a lot in a short time.

Each email says what happened and what to do, with a link to the log. The same alert isn't repeated more than once an hour.

> [!TIP]
> Got an alert you don't recognise? Open the link, see who did it, and if it wasn't them, use **Lock account** on that user (see [Users and roles](guide:panel/users-roles)).

## Pruning the log

The log keeps a year of history and tidies itself every day. If your role allows it, **Prune…** deletes older lines yourself: type how many days to keep, and confirm. Pruning can't be undone, and it's recorded and emailed as a security alert, since deleting history could hide someone's tracks.

> [!TECHNICAL]
> How long the log is kept is `activity.keep_days` in `config/sunrice-admin.php`; alert recipients are `security.alerts`. See [Activity log](docs:panel/activity) and [Security](docs:panel/security).
