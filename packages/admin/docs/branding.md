# Branding

**Settings → Branding** gives the panel its own name, logo, typeface and colour, and the sign-in page its own wording and image. It is part of every panel, like Settings → Security, and editing it needs `sunrice.settings.branding` (a [privileged permission](permissions.md)). It changes the admin only, never the website.

| Setting | Shown | When empty |
| --- | --- | --- |
| Panel name | The sidebar, the sign-in page, browser tabs, emails (login codes, password resets, test emails, security alerts) and the AI server's name | `$panel->brand()` |
| Tagline | Under the name in the sidebar and above the sign-in form | Nothing |
| Logo (an image from the media library) | The sidebar and the sign-in page, as **Sidebar shows** says | The name's first letter on the brand colour |
| Browser icon (an image: ICO, PNG or SVG) | Browser tabs and bookmarks, on every panel page including sign-in, and the collapsed sidebar | The logo when it is square-ish, else the name's first letter on the brand colour |
| Sidebar shows | What the top of the sidebar and the sign-in page show: see [below](#what-the-sidebar-shows) | Automatic |
| Font | Everywhere in the admin | The `sunrice-admin.font` config (Instrument Sans) |
| Brand colour (`#rrggbb`) | Primary buttons, the active menu item, focus rings | The neutral palette |
| Sign-in page: layout | A side panel next to the form (`split`), or the form alone (`centered`) | `split` |
| Sign-in page: small heading, headline, text, note at the bottom | The side panel (each new line of the headline starts a lighter line) | Built-in wording |
| Sign-in page: image | The side panel, instead of the built-in artwork | The artwork |

The log-in, two-factor code, forgot-password and reset-password pages all use the same layout.

## What the sidebar shows

**Sidebar shows** (`brandDisplay`) chooses the top of the sidebar, and the brand above the sign-in form and in its side panel. A logo is *wide* when it is more than 1.25 times as wide as tall (a wordmark); the *square mark* is the browser icon, else the logo when it is square-ish, else the lettermark (the name's first letter on the brand colour), the same order as the [browser icon](#the-browser-icon).

| Choice | Expanded sidebar | For |
| --- | --- | --- |
| Automatic (`auto`, the default) | A wide logo alone, at its own proportions; otherwise the logo (or the square mark) beside the name and tagline | Installs from before the setting look as they did |
| Logo and name (`logo_name`) | The logo beside the name and tagline; a wide logo gives way to the square mark | A square logo, or a wide one with the name written out |
| Logo only (`logo`) | The logo alone, at its own proportions | A wordmark that holds the name. Without a logo it is *Logo and name* |
| Icon and name (`icon_name`) | The square mark (the browser icon first) beside the name and tagline | A wide logo for the website, the icon in the admin |
| Name only (`name`) | The name and tagline, no image | A name that's brand enough |

**The collapsed sidebar** (the icon rail) has room for a square only, so whatever the choice it shows the square mark: the browser icon, else a square-ish logo, else the lettermark. It is the same image as the browser tab, never blank and never a squashed wide logo. On a phone the sidebar opens as a drawer, expanded, so it follows the choice.

The form draws each choice with the form's own, unsaved name, logo, icon and colour, and the collapsed square beside them. The images come from `GET admin/branding/preview` (route `sunrice.admin.branding.preview`, needs `sunrice.settings.branding`), which runs the same code as the sidebar and the favicon and saves nothing.

## The browser icon

The admin layout links the icon (`<link rel="icon">`) on every panel page, the sign-in pages included. It is the square mark, as in the collapsed sidebar: the **Browser icon** when one is chosen; otherwise the logo, when its width and height are within a quarter of each other (an SVG's size is read from its `viewBox`, or its `width` and `height`, when it's uploaded; an older SVG without a known size is taken as square); otherwise a lettermark: the branded name's first letter on the brand colour (the neutral one without), drawn as SVG by `GET admin/branding/icon.svg` (route `sunrice.admin.branding.icon`). That route needs no sign-in, because the sign-in page uses it, and runs without the session, so browsers and proxies may cache it: the layout's URL carries a version that changes with the letter and colour, and is cached for good. It shows nothing but that letter and colour.

When the chosen icon or the square logo is a raster image (PNG, JPEG, WebP…), the layout also links it as the `apple-touch-icon` for home screens.

## Fonts

The panel's font is **Instrument Sans**, built into its assets (`resources/fonts`, SIL Open Font License): one variable file per script, weights 400 to 600, served with the panel's CSS from your own domain, so by default no page asks a font service. A browser loads only the latin file (30 kB) for most text, and the latin-ext one (11 kB) when a page needs it.

The font list (`BrandingSettings::FONTS`) is Inter, Instrument Sans (built in), Manrope, DM Sans, Plus Jakarta Sans, Figtree, Outfit, IBM Plex Sans, Source Sans 3 and Nunito Sans, the others loaded from Bunny Fonts (400, 500, 600), and **System**, which loads nothing and uses the operating system's typeface. Leaving the font empty keeps what `config/sunrice-admin.php` says:

```php
'font' => [
    'stylesheet' => null,   // a web font's stylesheet, loaded on every admin page
    'family' => null,       // e.g. 'Brand Sans', the --sunrice-font (null: Instrument Sans)
],
```

Use the config for a font that isn't on the list (self-hosted, say). The panel's [Content-Security-Policy](security.md#content-security-policy) allows the chosen font's origin (`fonts.bunny.net` for the listed fonts other than Instrument Sans and System, or the config stylesheet's) in `style-src` and `font-src`; a stylesheet that loads its fonts from yet another origin needs it in `security.csp.sources`.

**Privacy and offline installs.** Every listed font except Instrument Sans and System is a request from each admin's browser to `fonts.bunny.net`, a third party (Bunny Fonts doesn't log IP addresses, but your privacy policy may still need to mention it). When that request is blocked (an intranet, a firewall, a server without internet), the panel quietly falls back to the system font, so a chosen font seems to do nothing. For these installs keep the default or choose **System**, or self-host the font and point the config at it:

```php
'font' => [
    'stylesheet' => '/fonts/inter.css',   // your own @font-face rules, in public/
    'family' => 'Inter',
],
```

The Font field's help says this too.

## The brand colour

The admin layout turns the colour into CSS variables in a `<style>` block in the page's `<head>` (with the request's [CSP nonce](security.md#content-security-policy)), so it applies before the first paint:

- `--primary` and `--sidebar-primary` are the colour; `--primary-foreground` and `--sidebar-primary-foreground` are black (`#111111`) or white, whichever has more contrast on it (WCAG relative luminance, `Branding::readableOn()`).
- `--ring` mixes the colour with the background (`color-mix(in oklch, …)`); `--accent-tint` and `--ring-glow` follow from `--primary` and `--ring` as always.
- In dark mode a dark colour is mixed with white until it stands out on the dark surfaces, and gets its own foreground.

Only a validated `#rrggbb` colour and a font from the list are ever printed; anything else stored is ignored. Saving the section reloads the whole page so the new look shows.

## In code

The name people see is `$panel->brandName()` (`@api`): the branding's name, else `$panel->brand()`. Use it wherever a plugin names the panel (in an email, say); it reads the settings, one cached query per request for all sections. `$panel->getBrand()` is only the name given in code.

```php
use Sunrice\Admin\Panel;
use Sunrice\Admin\Settings\BrandingSettings;

app(Panel::class)->brandName();              // 'Kebun Studio', or 'Acme' from ->brand('Acme')

$branding = app(BrandingSettings::class);   // the stored values; empty strings and nulls where unset
$branding->color;                            // '' or '#1e66f5'
```

Every admin page receives the branding with the defaults filled in, as `sunrice.branding`:

```ts
const { branding } = usePage<{ sunrice: { branding: Branding } }>().props.sunrice;
// { name, tagline, logo: url | null, logoWide: bool,
//   display: 'logo_name' | 'logo' | 'icon_name' | 'name',   // Sidebar shows, resolved against the logo
//   mark: url | null,   // the square mark (browser icon, else a square logo); null: the lettermark
//   lettermark: 'A', color: '#1e66f5' | null,
//   login: { layout, eyebrow, headline, text, footer, image: url | null } }
```

`sunrice.brand` (the name) is still shared too.

## The user menu

The foot of the sidebar shows who is signed in. Its menu switches the admin between **Light**, **Dark** and **System** appearance (kept per browser, applied before the first paint) and logs out.
