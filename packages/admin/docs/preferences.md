# Preferences

Each user's own choices in the panel are kept on the server, per user and key, as JSON: which columns a table shows and how many rows per page, so they follow the person from browser to browser. Resource tables use them (the **Columns** menu and the page size), and so does the CMS's entries list.

## In PHP

`Sunrice\Admin\Preferences\Preferences` (resolve it from the container):

```php
use Sunrice\Admin\Preferences\Preferences;

$preferences = app(Preferences::class);
$preferences->set($user, 'shop.orders.view', ['grouped' => true]);
$preferences->get($user, 'shop.orders.view', ['grouped' => false]);   // the default when not set
$preferences->set($user, 'shop.orders.view', null);                    // forgets it
$preferences->table($user, 'table.shop.orders');                       // ['columns' => ['number', 'total']|null, 'perPage' => 50|null]
```

Keys are letters, digits, dots, dashes and underscores, up to 100 characters (`Preferences::KEY_PATTERN`); start yours with your plugin's id (`table.shop.orders`, `shop.orders.view`). A value is at most 8 KB of JSON (`Preferences::MAX_BYTES`). A user's preferences go when the user is deleted.

Tables keep `{"columns": [...], "perPage": 25}` (`table()` reads it, with null for what wasn't chosen); resource tables use the key `table.resources.{key}`, the CMS's entries list `table.cms.entries.{collection}`. Read the page size on the server so the first page already has it, and let `?per_page=` win.

## In the browser

The panel answers `GET admin/preferences/{key}` with `{"key": …, "value": …}` and keeps `PUT admin/preferences/{key}` with a body `{"value": …}` (null forgets it). Both need sign-in and panel access, and only ever touch the signed-in user's own.

For a table, `useTablePreferences` from `@imajiner/admin-sdk/ui` does it for you:

```tsx
import { useTablePreferences } from '@imajiner/admin-sdk/ui';

// `initial`: what the page's props already hold (the server read it); leave it out to fetch it.
const { preferences, setColumns, setPerPage } = useTablePreferences('table.shop.orders', props.preferences);

await setColumns(['number', 'total']);   // then reload the rows if the server builds them per column
setPerPage(50);
```
