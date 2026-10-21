# Panel languages

The panel speaks the language each user picks, apart from the website's content languages: the panel can be in English and Indonesian while the site is in English and Chinese. A user picks theirs under their name at the foot of the sidebar (**Panel language**); the sign-in page uses the one last picked on that browser.

## Configuration

```php
// config/sunrice-admin.php
'locales' => ['en' => 'English', 'id' => 'Bahasa Indonesia'],   // the languages users may pick
'locale' => env('SUNRICE_ADMIN_LOCALE', 'en'),                   // for those who haven't picked one
```

On the panel's pages, `app()->getLocale()` is the user's panel language (`Sunrice\Admin\Support\PanelLocale`, `@api`); the website keeps its own. The pick is a [preference](preferences.md) (`locale`), set with `PUT /admin/language` (`locale`) or `SetPanelLanguage::handle($user, 'id')` (`@api`).

## Translations

The words are Laravel [JSON translations](https://laravel.com/docs/localization#using-translation-strings-as-keys): the English text is the key, and each package ships `lang/{code}.json` (the panel's and the CMS's have `id.json`). Your app's `lang/{code}.json` overrides their lines and adds languages: add a code to `locales` and a `lang/{code}.json` with the panel's words.

- **PHP**: `__('Saved.')`, `__('":title" added.', ['title' => $title])`. What you define in English for the panel is said in the user's language when it's sent to the page: field labels, help and placeholders, section and card titles, column and filter labels, options, actions, navigation items and groups, resource and settings labels, permission labels, and flash messages (`->with('success', 'Saved.')`). Write them in English and put their translations in your `lang/{code}.json`. Labels that are data stay as written with `->verbatim()` (navigation items, `Select` and `CheckboxList` options).
- **React**: `t('Save')`, `t('Delete :title?', { title })` from `@sunrice/admin-sdk` (the panel's own code: `@/lib/i18n`). The page carries the words for the user's language (`window.sunriceTranslations`), so changing language reloads it. `:Name` and `:NAME` capitalise the value, as in Laravel.

A plugin ships its own `lang/{code}.json` and registers it in its service provider:

```php
$this->loadJsonTranslationsFrom(__DIR__.'/../lang');
```

Every word the panel and the CMS show has an Indonesian line; `tests/Arch/TranslationsTest.php` checks it. Where English reads better or a translation would confuse (*Slug*, *Handle*, *Blueprint*, *SEO*), the Indonesian line keeps the English.
