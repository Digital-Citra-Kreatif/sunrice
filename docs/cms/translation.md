# Machine translation

`sunrice:translate` translates content, and the app's own language files,
with a language model: Google's Gemini or any model on OpenRouter.

```bash
php artisan sunrice:translate                    # everything into every other language
php artisan sunrice:translate --to=id --only=entries --collection=blog
php artisan sunrice:translate --dry-run          # count what would be sent
```

## Setting it up

Put a key in `.env`:

```dotenv
SUNRICE_TRANSLATE_DRIVER=gemini     # or openrouter
GEMINI_API_KEY=...
OPENROUTER_API_KEY=...
SUNRICE_TRANSLATE_MODEL=            # optional; else the driver's model in config
```

The rest is in `sunrice-cms.translation`: each driver's model, how many
strings go in one request (`batch_size`) and how long to wait for an
answer (`timeout`).

## What it translates

| `--only`  | What                                                    | Where it goes                         |
|-----------|---------------------------------------------------------|---------------------------------------|
| `entries` | title, fields that differ per language, SEO title and description | the translation's **draft**, created when missing |
| `terms`   | title, fields and SEO                                   | saved, live at once                  |
| `globals` | fields that differ per language                         | saved, live at once                  |
| `menus`   | link labels                                             | saved, live at once                  |
| `lang`    | `lang/<from>/*.php` (subfolders too) and `lang/<from>.json` | `lang/<to>/…` and `lang/<to>.json` |

Entries never change on the site by themselves: review the drafts in the
admin and publish them as usual. A slug still the same as the source's
follows the translated title.

Inside fields it translates text, textarea and rich text, a link's label,
and the same in groups, repeater rows and flexible blocks. Fields shared
by all languages, and nested fields marked `translatable: false`, are
never sent. Translations go through the same actions as the admin, so they
are validated and rich text is sanitised like an editor's.

## What it leaves alone

Something counts as translated when it isn't empty and differs from the
source language (a new translation starts as a copy). That is left as it
is, so running the command again only sends what's new. `--force` sends
everything again and asks first, since it replaces what people may have
edited.

`--from` translates from another language than the main one; `--driver`
and `--model` override the configuration for one run. The command fails
(exit code 1) when anything couldn't be translated or saved, and says
which.

## Your own translator

Bind `Sunrice\Cms\Translation\Translator` in the container to translate
with something else (a translation service, or a fake in tests):

```php
$this->app->bind(Translator::class, DeepLTranslator::class);
```

It gets strings by key and returns the translations by the same keys; a
key left out counts as failed.

# Switching the main language

The main language is the first in `sunrice-cms.locales`: its pages have
no prefix (`/about`, the others `/id/about`) and it holds the fields
shared by all languages (images, toggles, numbers…). To make another one
the main language once content exists, convert the content first, then
change the config:

```bash
php artisan sunrice:switch-main-language id --dry-run   # what blocks it, what changes
php artisan sunrice:switch-main-language id
# then: SUNRICE_LOCALES=id,en
```

The command:

- copies the shared fields of every entry (live and draft), term, global
  set and listing page to that language, which holds them from then on;
- adds redirects (301) for the addresses that change: `/about` →
  `/en/about` and `/id/tentang` → `/tentang`; redirects that led to the old
  addresses follow;
- lists entries live in the main language but not published in the new
  one: they won't be on the site at their main address until they are.

Entries and terms with no version in the new language stop it. Translate
them first (`sunrice:translate --to=id`), or pass `--copy-missing` to give
them a copy of the main language's content, published as it is.

Nothing on the site changes until the config does, and the site's caches
start over when the languages change. Back up the database first; `--force`
skips the question.
