# Branding

**Settings → Branding** gives the panel its own name, logo, typeface and colour, and the sign-in page its own wording and image. It is part of every panel, like Settings → Security, and editing it needs `sunrice.settings.branding` (a [privileged permission](permissions.md)). It changes the admin only, never the website.

| Setting | Shown | When empty |
| --- | --- | --- |
| Panel name | The sidebar, the sign-in page, browser tabs | `$panel->brand()` |
| Tagline | Under the name in the sidebar and above the sign-in form | Nothing |
| Logo (an image from the media library) | The sidebar and the sign-in page | The name's first letter on the brand colour |
| Font | Everywhere in the admin | The `sunrice-admin.font` config (Inter) |
| Brand colour (`#rrggbb`) | Primary buttons, the active menu item, focus rings | The neutral palette |
| Sign-in page: layout | A side panel next to the form (`split`), or the form alone (`centered`) | `split` |
| Sign-in page: small heading, headline, text, note at the bottom | The side panel (each new line of the headline starts a lighter line) | Built-in wording |
| Sign-in page: image | The side panel, instead of the built-in artwork | The artwork |

The log-in, two-factor code, forgot-password and reset-password pages all use the same layout.

## Fonts

The font list (`BrandingSettings::FONTS`) is Inter, Instrument Sans, Manrope, DM Sans, Plus Jakarta Sans, Figtree, Outfit, IBM Plex Sans, Source Sans 3 and Nunito Sans, loaded from Bunny Fonts (400, 500, 600), and **System**, which loads nothing and uses the operating system's typeface. Leaving the font empty keeps what `config/sunrice-admin.php` says:

```php
'font' => [
    'stylesheet' => 'https://fonts.bunny.net/css?family=inter:400,500,600&display=swap',
    'family' => null,   // e.g. 'Brand Sans', the --sunrice-font
],
```

Use the config for a font that isn't on the list (self-hosted, say). A strict `Content-Security-Policy` needs `fonts.bunny.net` in `style-src` and `font-src` for the listed fonts.

## The brand colour

The admin layout turns the colour into CSS variables in a `<style>` block in the page's `<head>` (with the CSP nonce when `Vite::useCspNonce()` set one), so it applies before the first paint:

- `--primary` and `--sidebar-primary` are the colour; `--primary-foreground` and `--sidebar-primary-foreground` are black (`#111111`) or white, whichever has more contrast on it (WCAG relative luminance, `Branding::readableOn()`).
- `--ring` mixes the colour with the background (`color-mix(in oklch, …)`); `--accent-tint` and `--ring-glow` follow from `--primary` and `--ring` as always.
- In dark mode a dark colour is mixed with white until it stands out on the dark surfaces, and gets its own foreground.

Only a validated `#rrggbb` colour and a font from the list are ever printed; anything else stored is ignored. Saving the section reloads the whole page so the new look shows.

## In code

```php
use Sunrice\Admin\Settings\BrandingSettings;

$branding = app(BrandingSettings::class);   // the stored values; empty strings and nulls where unset
$branding->color;                            // '' or '#1e66f5'
```

Every admin page receives the branding with the defaults filled in, as `sunrice.branding`:

```ts
const { branding } = usePage<{ sunrice: { branding: Branding } }>().props.sunrice;
// { name, tagline, logo: url | null, lettermark: 'A', color: '#1e66f5' | null,
//   login: { layout, eyebrow, headline, text, footer, image: url | null } }
```

`sunrice.brand` (the name) is still shared too. Emails (security alerts, login codes) and the AI server keep using `$panel->brand()`.

## The user menu

The foot of the sidebar shows who is signed in. Its menu switches the admin between **Light**, **Dark** and **System** appearance (kept per browser, applied before the first paint) and logs out.
