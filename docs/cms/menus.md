# Menus

A **menu** (main, footer…) holds nested links: to an **entry**, which follows the entry's title and address in each language, or to an **address** (a web address, a path on the site, `mailto:` or `tel:`).

## Setting up and editing

Under **Structure → Menus** (permissions `cms.menus.view`, `.create`, `.update`, `.delete`): a title and a handle that templates use.

Under **Content → Menus** (`cms.menus.view` to see, `cms.menus.update` to save): add entries and links, give them labels in each language, open them in a new tab, nest them (three levels), reorder and remove them; a link takes what's under it along. The menu is saved as a whole.

An entry's label is optional: without one, the menu shows the entry's title. An address needs a label in the main language; other languages fall back to it. Addresses that could run code (`javascript:`, `data:`…) are refused.

In code: `SaveMenu` replaces a menu's links with a tree:

```php
app(SaveMenu::class)->handle($menu, [
    ['entry_id' => 12, 'children' => [
        ['entry_id' => 15, 'labels' => ['en' => 'Our team', 'id' => 'Tim kami']],
    ]],
    ['url' => 'https://shop.example.com', 'labels' => ['en' => 'Shop'], 'new_tab' => true],
]);
```

## In templates

```blade
<nav>
    @foreach (Cms::menu('main') as $item)
        <a href="{{ $item['url'] }}" @if ($item['new_tab']) target="_blank" rel="noopener" @endif>{{ $item['label'] }}</a>
        @foreach ($item['children'] as $child) … @endforeach
    @endforeach
</nav>
```

Links come in the current language. Entries that aren't on the site (drafts, scheduled, trashed, in a collection without a URL) are left out, with the links under them. An unknown menu gives an empty list. Menus load once per request.
