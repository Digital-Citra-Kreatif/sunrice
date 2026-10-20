# Lumina Estates: test brief

We are testing the **new Sunrice** (`/home/user/sunrice`, packages `sunrice/admin` + `sunrice/cms`, current branch) by building a real, professional website for a real estate company on it, wearing three hats: **developer**, **admin**, **editor**. The site is a normal Laravel 13 app at `/home/user/realestate` that requires the packages from `../sunrice/packages/*` (symlinked: code changes in the packages show up at once).

**Do not change anything in `/home/user/sunrice`.** We are testing it, not fixing it. Every problem goes in your findings file.

## The company

**Lumina Estates**, a premium residential agency in Indonesia (Jakarta and Bali): homes, villas, apartments and land for sale and rent, a team of agents, neighbourhood guides, a journal of market insights. Bilingual: **English** (main) and **Indonesian** (`id`). Tone: calm, confident, luxurious; generous whitespace, a refined serif for headings with a clean sans for text, a warm neutral palette with one deep accent (e.g. deep teal or forest green), large photography.

## Running

- Site: `http://127.0.0.1:8200` (`php artisan serve`, 6 workers), admin at `/admin`. MySQL 8 (`lumina` database). Queue worker and scheduler run in the background (logs in `storage/logs/`).
- PHP: `/opt/php84/bin/php` (the system `php` is 8.3 and can't run this). Artisan: `cd /home/user/realestate && /opt/php84/bin/php artisan …`.
- Super admin: `ayu@lumina-estates.test` / `Lumina!Admin2026`.
- Browser automation: Playwright via `playwright-core` already installed at `/tmp/claude-0/-home-user/f810cfc1-6e1b-5a9c-8d44-3ce703373f7b/scratchpad/qa/node_modules` (require it by that path, or `NODE_PATH`); Chromium at `/opt/pw-browsers/chromium-1194/chrome-linux/chrome` (`chromium.launch({ executablePath })`). Never run `playwright install`. Write your scripts under `/home/user/realestate/qa/scripts/`.
- **Photos:** Unsplash is blocked by this environment's network policy right now (`images.unsplash.com` → 403). Do not substitute other photos for the site. Where a photo belongs, leave the field empty, and list wanted Unsplash photos (search terms or photo ids) in `qa/unsplash-wanted.md` so they can be downloaded and imported in one go later. For testing uploads themselves you may generate plain test images locally (e.g. with PHP GD or ImageMagick) and must delete them from the media library afterwards; never present them as site content.

## Recording findings (everyone)

Keep your own file `qa/findings-<hat>.md` (admin, developer, editor, frontend, security). For every finding:

```
### <short title>
- Severity: critical | high | medium | low | polish
- Area: install | docs | admin UI | editor UX | structure | site | API | security | performance | i18n | a11y
- Where: URL / screen / file / command
- Steps: 1. … 2. …
- Expected: …
- Actual: …
- Screenshot: qa/screenshots/<hat>-<nn>-<slug>.png (when it shows something)
- Note: workaround used, or why it matters
```

Record **weird instructions in the docs**, **UI that confuses or looks off**, **bugs**, **security problems**, **slow pages** (note query counts or timings), **missing features** you needed. Also note what worked well, briefly, at the end of your file. Take screenshots generously (`qa/screenshots/`, PNG, 1440×900 desktop unless the issue is mobile).

## Content model (everyone builds against exactly these handles)

Languages: `en` (main), `id`. Handles are fixed; titles/labels as written.

### Taxonomies
| handle | title | nested | route (term pages) | notes |
| --- | --- | --- | --- | --- |
| `property-types` | Property types | no | `/property-type/{slug}` | House, Villa, Apartment, Land, Commercial |
| `listing-types` | Listing types | no | — | For sale, For rent |
| `locations` | Locations | yes | `/location/{slug}` | Jakarta › South Jakarta › Kemang, Pondok Indah; Jakarta › Central Jakarta › Menteng; Bali › Badung › Canggu, Seminyak; Bali › Gianyar › Ubud |
| `amenities` | Amenities | no | — | Private pool, Garden, Gym, 24h security, Parking, Ocean view, Rice-field view, Smart home, Staff quarters, Rooftop |
| `topics` | Journal topics | no | `/journal/topic/{slug}` | Market reports, Buying guides, Living, Company news |

### Fieldsets
- `seo-intro`: not needed. Keep fieldsets to: `contact-person` (fields: `name` text, `role` text, `phone` text, `email` text) used by globals, and `cta` (fields: `heading` text, `text` textarea, `button` link) used as a flexible block.

### Blueprints
- `page` (Pages): `sections` **flexible** with blocks (each block is a fieldset you create): `hero` (`heading` text, `subheading` textarea, `image` media single, `button` link, `secondary_button` link), `rich-text` (`body` rich_text), `image-text` (`heading` text, `body` rich_text, `image` media, `image_side` select left/right), `stats` (`items` repeater: `value` text, `label` text), `featured-properties` (`heading` text, `properties` entries from `properties`, multiple, max 6), `team` (`heading` text, `agents` entries from `agents`, multiple), `testimonials` (`heading` text, `testimonials` entries from `testimonials`, multiple), `cta` (the fieldset above), `faq` (`items` repeater: `question` text, `answer` rich_text). Also top-level `intro` textarea.
- `property`: `summary` textarea (max 300); `price` number (step 1); `price_suffix` select (none / per month / per year); `status` select (available / under offer / sold / rented, default available); `bedrooms` number; `bathrooms` number; `building_area` number (m²); `land_area` number (m²); `year_built` number; `address` text; `map` link; `gallery` media multiple images (max 12); `floor_plan` media single; `description` rich_text; `features` repeater (`label` text); `agent` entries from `agents` (single); `neighbourhood` entries from `neighbourhoods` (single); `video` link; `featured` toggle; terms fields `property_type` (property-types, single), `listing_type` (listing-types, single), `location` (locations, single), `amenities` (amenities, multiple). Non-translatable (shared): price, price_suffix, status, numbers, gallery, floor_plan, map, agent, neighbourhood, featured, terms. Translatable: summary, description, features, address.
- `agent`: `role` text, `photo` media, `phone` text, `email` text, `whatsapp` text, `languages` select multiple (English, Indonesian, Mandarin, Dutch), `bio` rich_text, `social` repeater (`network` select Instagram/LinkedIn/Facebook, `url` link).
- `neighbourhood`: `hero` media, `intro` textarea, `body` rich_text, `highlights` repeater (`label` text, `text` textarea), `average_price` number.
- `post` (Journal): `cover` media, `excerpt` textarea (max 240), `body` rich_text, `author` entries from `agents` (single), `topics` terms (topics, multiple), `related` entries from `properties` (multiple, max 3).
- `testimonial`: `quote` textarea, `client` text, `client_role` text, `photo` media, `rating` number (1–5).
- Listing blueprints: `properties-listing` (`intro` textarea, `hero` media), `journal-listing` (`intro` textarea).
- Global blueprints: `company` (`legal_name` text, `tagline` text, `phone` text, `whatsapp` text, `email` text, `address` textarea, `office_hours` textarea, `map` link, `primary_contact` fieldset include `contact-person`), `social` (`links` repeater: `network` select, `url` link), `footer` (`about` textarea, `legal` rich_text, `newsletter_heading` text).
- Form blueprints: `contact-form` (`name` text required, `email` text required email, `phone` text, `topic` select Buying/Selling/Renting/Other, `message` textarea required), `inquiry-form` (`name`, `email`, `phone`, `property` text (the property's title, prefilled by the template), `preferred_date` date, `message` textarea), `valuation-form` (`name`, `email`, `phone`, `address` text required, `property_type` select, `bedrooms` number, `photos` file (images, max 5 MB), `notes` textarea), `newsletter-form` (`email` text required email).

### Collections
| handle | title | blueprint | route | listing route | structure | other |
| --- | --- | --- | --- | --- | --- | --- |
| `pages` | Pages | page | `/{slug}` | — | hierarchical, ordered | homepage = page "Home" (Settings → Site) |
| `properties` | Properties | property | `/properties/{slug}` | `/properties` (`properties-listing`, 9 per page) | — | schema type Web page; SEO from fields: title←(none), description←summary, image←gallery |
| `agents` | Agents | agent | `/team/{slug}` | `/team` | ordered | |
| `neighbourhoods` | Neighbourhoods | neighbourhood | `/neighbourhoods/{slug}` | `/neighbourhoods` | — | |
| `journal` | Journal | post | `/journal/{slug}` | `/journal` (`journal-listing`, 6 per page) | dated | comments allowed; schema type Blog post |
| `testimonials` | Testimonials | testimonial | none | — | ordered | |

Taxonomies attached: properties ← property-types, listing-types, locations, amenities; journal ← topics.

### Globals: `company`, `social`, `footer` (blueprints above).
### Menus: `main` (Properties, Neighbourhoods, Team, Journal, About, Contact; Properties has children: For sale, For rent → listing with filters via URL, Villas → term page), `footer`, `legal` (Privacy, Terms).
### Forms: `contact` → contact-form (email to ayu@…, thank-you message, redirect none), `inquiry` → inquiry-form, `valuation` → valuation-form (captcha off; per-minute limit 3), `newsletter` → newsletter-form.
### Pages: Home, About (with a child "Our story"), Services (children: Buy, Sell, Rent, Property management), Sell with us (valuation form), Contact, Privacy policy, Terms.
### Redirects: `/listings` → `/properties` (301); `/blog` → `/journal` (301).
### People and roles
- Ayu Pratiwi: super admin (exists).
- Budi Santoso `budi@lumina-estates.test`: **editor** role.
- Citra Lestari `citra@lumina-estates.test`: **author** role (writes journal posts only, publishes nothing).
- Dewi Kartika `dewi@lumina-estates.test`: **translator** role.
- Eko Wibowo `eko@lumina-estates.test`: custom role "Listings manager": properties (all actions), media, terms of locations/amenities; nothing else.
Passwords: `Lumina!<FirstName>2026`.

## Actual handles (the panel refused some of the above; these are what exist)

Hyphens are not allowed in handles, so underscores replace them: `property_types`, `listing_types`, `contact_person`, `rich_text` (block), `image_text`, `featured_properties`, `properties_listing`, `journal_listing`, `contact_form`, `inquiry_form`, `valuation_form`, `newsletter_form`. Reserved field handles were renamed: the property's `status` is **`property_status`**, the post's `author` is **`author_agent`**. The company global's contact person is a group: **`primary_contact.{name,role,phone,email}`**. Everything else is as above; see the "Setup summary" at the end of `qa/findings-admin.md` for the full list.
