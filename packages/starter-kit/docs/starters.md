# Starter sites

A **starter** is a whole site to begin from: its structure (blueprints, fieldsets, collections, taxonomies, global sets, forms, menus, redirects), sample content (entries, terms, global values), the media files that content uses, its Blade templates, its CSS, fonts and images, and its own words. One command installs it on a fresh Sunrice app; everything it brings is then the site's to edit.

The starter kit (`sunrice/starter-kit`) installs starters, makes them from a site, and comes with a small **Blog** starter to try and to copy from. It's a separate package: add it when you build or install starters, and leave it out otherwise. Ready-made starters (each a package of its own, like `sunrice/starter-real-estate`) are developed in [Digital-Citra-Kreatif/sunrice-starters](https://github.com/Digital-Citra-Kreatif/sunrice-starters).

```bash
composer require sunrice/starter-kit
php artisan sunrice:starters                 # the starters this app can install
php artisan sunrice:starter blog --dry-run   # what installing would do
php artisan sunrice:starter blog             # install it
```

To show this guide on Help → Docs, add the kit's plugin to the panel (the commands work without it):

```php
$panel->plugin(CmsPlugin::make())->plugin(\Sunrice\StarterKit\StarterKitPlugin::make());
```

## What a starter is

A folder:

```
my-starter/
├── starter.json          the manifest
├── site.json             structure and sample content (sunrice:export-structure's format, entries included)
├── media.json            the media files' titles, alt text and captions
├── media/                the media library's files the content uses
│   └── hero.jpg
├── views/                copied into resources/views
│   ├── cms/              the site's templates: layout, entry, listing, term, search, partials, blocks
│   └── errors/           error pages
├── public/               copied into public (CSS, fonts, images the templates use)
│   └── cms/site.css
└── lang/                 copied into lang (the CMS's site words, overridden or in more languages)
    └── vendor/sunrice-cms/id/site.php
```

Only `starter.json` is required. `starter.json`:

```json
{
    "name": "my-starter",
    "title": "My starter",
    "description": "One sentence: what kind of site it is.",
    "locales": ["en", "id"]
}
```

- `name`: lowercase letters, digits and dashes; what `sunrice:starter <name>` takes.
- `locales`: the languages its texts are in, shown by `sunrice:starters`. Installing on a site without one of them stops before anything is saved, naming it; `--skip-other-languages` installs the rest and leaves those texts out.

`site.json` is a structure file ([Structure → Export and import](../../cms/docs/structure.md)) with its content: whatever `sunrice:export-structure --entries` writes. What field values point at is portable: an entry as `{"entry": "pages/about"}`, a term as `{"term": "topics/news"}`, a media file as `{"media": "hero.jpg"}`, the name of a file in `media/`. Published entries keep their `published_at`.

`media.json` gives each file's details by name; files without an entry get their name as title:

```json
{
    "hero.jpg": {"title": "Hero", "alt": "The bay at sunrise", "caption": "Photo: Ana", "translations": {"id": {"alt": "Teluk saat matahari terbit"}}}
}
```

## Installing one

`php artisan sunrice:starter <name>` (or `--path=<folder>` for a starter that isn't registered), in this order:

1. **Checks the whole structure first**, as a dry-run import: a blueprint that doesn't validate, a URL another collection has, or a language the site lacks stops it, and nothing is saved.
2. **Uploads its media files** the library doesn't have yet, through the panel's own upload action (the same checks as the admin, image sizes made on the queue), with their details. A file the library has, by name, is used as it is.
3. **Imports its structure and content** through `StructureImporter`: the admin's actions and checks. Items with the same handle are updated as an import does; entries and terms that exist are left alone; fieldsets and blueprints that would lose fields are refused unless `--force`.
4. **Copies its files**: `views/` into `resources/views`, `public/` into `public`, `lang/` into `lang`. A file the app has, different, is **kept** and listed; `--force` replaces it.

| Option | |
| --- | --- |
| `--dry-run` | Report what would happen: what the import would create or update, the files to copy, the media to upload. Nothing is saved. |
| `--no-content` | The structure only: no entries, terms or global values (menus' links to entries are left out). |
| `--force` | Replace the app's files that differ, and save fieldsets and blueprints that lose fields. |
| `--skip-other-languages` | Leave out its texts in languages the site doesn't have. |

Running it again adds only what's missing. Afterwards, choose the homepage and the site's name and logo in Settings → Site: settings, users and roles aren't part of a starter.

In code: `app(\Sunrice\StarterKit\Actions\InstallStarter::class)->handle($starter, dryRun: false, force: false, content: true)` returns an `Installation`: the import's `ImportReport` (`$installation->report`), the files by result (`files(Installation::COPIED)`, `UNCHANGED`, `KEPT`) and the media files `uploaded` and `reused`.

## Making one from a site

Build the site as you'd build any Sunrice site: blueprints in the builder, templates in `resources/views/cms`, CSS in `public/`, sample content in the admin. Then:

```bash
php artisan sunrice:make-starter my-starter --title="My starter" --description="A portfolio with projects." --public=site
```

It writes `starters/my-starter` (or `--to=<folder>`):

- `site.json`: the structure and content, with entries (drafts and whether each language is published, with dates);
- `media/` and `media.json`: every media file the content, listing pages or SEO cards use (not the whole library), each named as it was uploaded (`hero.jpg`), with its details;
- `views/cms` and `views/errors`: your templates;
- `public/<folder>` for each `--public` (your CSS, fonts and images; `public/vendor` and `public/storage` are never what you want here);
- `lang/vendor/sunrice-cms`: the CMS's site words you override.

A folder that isn't empty is refused unless `--force`, which replaces its contents. Check the starter on a fresh app: `php artisan sunrice:starter --path=starters/my-starter --dry-run`.

## Writing a good starter

- **Templates only use what the starter defines.** Name menus, globals and collections by the handles in `site.json` (`cms_menu('main')`, `cms_global('footer')`). Where something may be missing (a menu an editor deleted), render nothing rather than fail: `@if (($menu = cms_menu('main')) !== [])`.
- **Templates need nothing but the CMS.** A starter copies views, not PHP: a template that calls your app's own classes (`App\Support\…`) or routes breaks on another app. Use the CMS's helpers and components, or ship that code in the starter's Composer package (registered by its service provider).
- **Start from the CMS's templates.** `php artisan vendor:publish --tag=sunrice-cms-templates` gives a layout, entry, listing, term and search templates and partials that handle untranslated pages, SEO, menus and pagination. The Blog starter is exactly these, plus one block view.
- **A view per kind of block.** A flexible field's block of fieldset `quote` renders `cms/blocks/quote.blade.php` with `$block` when there is one (the Blog starter's `views/cms/blocks/quote.blade.php`), else its fields.
- **Assets under a folder of your own**, `public/<starter>/…` or `public/cms/…`, referenced with `asset()`. A starter that needs a build (Vite, Tailwind) ships the built files, and its sources in a folder of its own for those who want to change them.
- **Sample content that teaches.** Short texts that say what each field is for and where to change it ("Choose this page as the homepage in Settings → Site"), a draft to show drafts, and images light enough to keep the package small (JPEG around 70% quality, 1200 px wide is plenty).
- **Handles that last.** Field handles are stored with every entry; renaming them later needs `sunrice:rename-field` on every site that installed the starter.
- **Languages.** Write `locales` for the languages its texts are in. A multilingual starter carries each entry's translations in `site.json` and its site words in `lang/vendor/sunrice-cms/<locale>/site.php`.
- **No secrets.** A starter has no settings, so no keys or addresses leak, but check that sample content and form notification addresses are examples (`team@example.com`).

## Shipping one as a package

A starter can live in any Composer package, alone or beside the package's own code. Register its folder in the package's service provider; it's read only when a command asks:

```php
use Sunrice\StarterKit\Starters;

public function boot(): void
{
    $this->callAfterResolving(Starters::class, fn (Starters $starters) => $starters->register(dirname(__DIR__).'/starter'));
}
```

Require `sunrice/starter-kit` and the `sunrice/cms` version its blueprints and templates were made with. Then `composer require acme/portfolio-starter` and `php artisan sunrice:starter portfolio` installs it. Two registered starters with the same name are refused, naming both folders.

## Testing a starter

Install it in a test, the way the kit tests its own (`tests/StarterKit/StartersTest.php`): a Testbench app with the panel, the CMS and the kit, `Storage::fake('public')`, then `InstallStarter`, and assert on what visitors see:

```php
it('installs and shows the home page', function () {
    app(InstallStarter::class)->handle(Starter::load(dirname(__DIR__).'/starter'));

    get('/blog/hello-world')->assertOk()->assertSee('Hello, world');
});
```

Delete the copied `resources/views/cms`, `resources/views/errors` and `public/<folder>` after each test: they're written into the test app.
