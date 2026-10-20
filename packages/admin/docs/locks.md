# Edit locks

One person edits an item at a time. While an editor page is open, it holds the item's **edit lock** and renews it every 15 seconds; a lock nobody renews expires after 2 minutes, so a closed tab never blocks anyone for long. Someone who opens the item meanwhile is told who's editing it and since when, and can:

- **Go back**,
- **View only** (the page stays read-only, with a notice), or
- **Take over**: the other editor is told on their next renewal, their unsaved changes are kept for a month, and they're sent back. Whoever opens the item later sees the kept changes with **Load into the form** and **Discard**.

A person's own tabs never lock each other out. Saves are still checked against the item's version (see the CMS's [Save mine anyway](../../cms/docs/collections.md#editing-entries)), so an expired lock can't silently overwrite someone's work.

The CMS locks entries (`cms.entry`), global sets (`cms.global`) and terms' pages (`cms.term`, for those who manage the term's taxonomy), each per language.

## Locking your own pages

Register the type in your plugin's `register()` or panel provider, with who may edit an item of it (by id, and language: `''` when it has none). A plugin's types start with its id and a dot:

```php
use Illuminate\Contracts\Auth\Access\Authorizable;

$panel->lockable('shop.product', fn (string $id, string $locale, Authorizable $user): bool =>
    $user->can('shop.products.update') && Product::query()->whereKey($id)->exists());
```

Then, in the page:

```tsx
import { KeptEditsNotice, useEditLock, useUnsavedChanges, ViewingNotice } from '@sunrice/admin-sdk/ui';

const lock = useEditLock({
    type: 'shop.product', id: product.id, enabled: can.update, noun: 'product', leaveTo: adminUrl('shop/products'),
    getUnsaved: () => (form.isDirty ? { ...form.data } : null),
});
useUnsavedChanges(form.isDirty && !lock.takenOver, lock.readOnly ? undefined : save);

return (
    <form>
        {lock.dialogs}
        <ViewingNotice by={lock.viewingBy} />
        <KeptEditsNotice kept={lock.kept} onLoad={async (id) => form.setData(await lock.loadKept(id))} onDiscard={lock.discardKept} />
        <fieldset disabled={lock.readOnly}>…</fieldset>
    </form>
);
```

The endpoints behind it (`/admin/locks/{type}/{id}`, `/admin/kept-edits/{id}`) check the callback on every request. Locks live in the cache: set `locks.store` in `config/sunrice-admin.php` to a store every server shares. In PHP, `Sunrice\Admin\Locks\EditLocks` has `acquire()`, `takeOver()`, `release()` and `current()`; kept changes are `Sunrice\Admin\Locks\KeptEdit` rows, at most 5 per person and item (keeping another deletes the oldest), pruned daily after 30 days when the app runs the scheduler.
