# Findings: admin hat

Tester: admin (Ayu Pratiwi, super admin). Everything set up through http://127.0.0.1:8200/admin with Playwright (scripts in `qa/scripts/admin-*.mjs`). Database and HTML read only to verify.

## 1. Sign-in

### Wrong-password message is good, but the activity log line reads oddly
- Severity: polish
- Area: admin UI
- Where: /admin/login, Dashboard → Recent activity
- Steps: 1. Sign in with a wrong password. 2. Sign in correctly. 3. Look at the dashboard's Recent activity.
- Expected: "Failed sign-in for ayu@… " or similar.
- Actual: Form shows "These credentials do not match our records." under the email field (good, no account enumeration). The log shows "Someone login failed Ayu Pratiwi" and "Ayu Pratiwi logged in Ayu Pratiwi" (actor and subject concatenated into an ungrammatical sentence; "login" used as a verb).
- Screenshot: qa/screenshots/admin-02-login-wrong-password.jpg, qa/screenshots/admin-03-dashboard-first.jpg

### Page title repeats the name on the sign-in page
- Severity: polish
- Area: admin UI
- Where: /admin/login `<title>`
- Actual: "Log in to Lumina Estates · Lumina Estates".
- Expected: "Log in · Lumina Estates".

## 2. Branding

### Admin fonts load from fonts.bunny.net (external CDN), no local fallback warning
- Severity: low
- Area: admin UI / security (privacy)
- Where: Settings → Branding → Font (any choice, incl. the default Inter)
- Steps: 1. Choose Manrope, save. 2. Watch network.
- Expected: Fonts bundled with the panel (or a note that a third-party font CDN is contacted, which matters for GDPR-minded clients and offline/intranet installs).
- Actual: Every admin page requests `https://fonts.bunny.net/css?family=…`. Here the request is blocked so the panel silently falls back to the system font; the choice appears to "do nothing". The help text says nothing about it.
- Note: Also no serif options at all (fine for the admin), but the list does not hint that "System" avoids the CDN.

### Saving Branding answers HTTP 409 (Inertia full reload)
- Severity: polish
- Area: admin UI / performance
- Where: Settings → Branding → Save
- Steps: 1. Change name/colour/font. 2. Save.
- Expected: Normal Inertia response.
- Actual: The POST answers 409 Conflict (asset version changes with the branding) and the browser does a full page reload. The "Branding settings saved." toast still appears and data is saved (verified in `sunrice_settings`), so users only see a flash; it shows up as an error in the browser console though. The Guide (Appearance) says the whole page reloads on purpose, so this is by design; only the console error is noise.
- Screenshot: qa/screenshots/admin-06-branding-saved.jpg

### Brand colour validation message does not say the expected format
- Severity: polish
- Area: admin UI
- Where: Settings → Branding → Brand colour
- Steps: Type "teal-ish", save.
- Actual: "The Brand colour field format is invalid." (input is also silently cut to 7 characters: "teal-is"). The placeholder "#1e66f5" looks like a real value next to an empty, crossed-out swatch.
- Expected: "Use a hex colour like #0F4C4C."
- Screenshot: qa/screenshots/admin-07-branding-invalid-colour.jpg

### Logo: wide logo is squeezed into a square tile and also becomes the favicon
- Severity: medium
- Area: admin UI
- Where: Settings → Branding → Logo; sidebar; sign-in page; browser tab
- Steps: 1. (`public/images/lumina-logo.svg` was missing at first, appeared later.) 2. Media → Upload `lumina-logo.svg` (208×56 wordmark) – accepted. 3. Branding → Logo → Choose → lumina-logo → Save.
- Expected: a wide logo shown at its proportions in the sidebar/sign-in header (replacing the name, since it contains the wordmark); the favicon stays the "L" because the logo is not square (that's what the Browser icon help text promises: "Empty: a square logo, else the name's first letter").
- Actual: the logo is shrunk into the 32 px square tile next to the name (an unreadable sliver, the name printed again beside it); the Branding preview crops it to "UM / TATES"; and the favicon becomes the wide SVG (`<link rel="icon" href="/storage/media/…/lumina-logo.svg">`), i.e. a tiny unreadable stripe in the tab. Only a square logo really works, which the help text says ("A square SVG or PNG works best"), but the BRIEF's / any real company's logo is wide.
- Screenshot: qa/screenshots/admin-95-branding-with-logo.jpg, qa/screenshots/admin-96-signin-with-logo.jpg
- Note: I removed it from Branding again (the "L" on teal looks better) and used the same file as Settings → Site → Structured data → Logo ("square or wide"), where it fits. The file stays in the media library.

### Branding works: colour and sign-in copy apply everywhere
- Severity: polish (positive)
- Area: admin UI
- Note: Brand colour (#0F4C4C) and sign-in copy apply correctly on the sign-in page, desktop and mobile; favicon without logo is a generated "L" SVG (`/admin/branding/icon.svg`). (qa/screenshots/admin-08-signin-branded.jpg, admin-09-signin-mobile.jpg)

### Two nested `<main>` landmarks on every admin page
- Severity: low
- Area: a11y
- Where: every admin page (`main[data-slot=sidebar-inset]` wraps `main.sunrice-page-enter`)
- Expected: one `main` landmark.
- Actual: two nested `main` elements; screen readers announce two main regions.

## 3. Settings

### Number fields in Settings show the previous value right after saving
- Severity: medium
- Area: admin UI
- Where: Settings → Comments → "Close comments after (days)" (likely every optional number setting)
- Steps: 1. Open Settings → Comments with the field empty. 2. Type 365. 3. Save ("Comments settings saved."). 4. Look at the field.
- Expected: the field shows 365.
- Actual: the field is empty again, although the database holds 365 (it appears after a reload). The reverse also happens: clear the field (365 → empty), save: the database now holds `null` (never close) but the field still shows 365. Typing into that stale field then gives "365365" on screen while the real state differs. An admin cannot trust what the page shows after saving.
- Screenshot: qa/screenshots/admin-21-comments-close-days-after-save.jpg, qa/screenshots/admin-15-comments-saved.jpg
- Note: Workaround: reload the page after saving to see the real value.

### Languages page starts with an empty box although the site already has two languages
- Severity: low
- Area: admin UI / i18n
- Where: Settings → Languages (/admin/settings/locales)
- Steps: open the page on a fresh install.
- Expected: the current languages listed (from config) so they can be edited.
- Actual: the textarea is empty, with a placeholder "en: en / id: id" (config codes shown as names). It is unclear whether empty means "no languages" or "use the config". After entering "en: English / id: Bahasa Indonesia" and saving, it works.
- Note: The URL is `/admin/settings/locales` but the menu says "Languages"; `/admin/settings/languages` and `/admin/settings/site-code` 404 (Site code lives at `/admin/settings/code`). Minor, but guessable URLs would help.
- Screenshot: qa/screenshots/admin-10-languages-before.jpg

### Languages are a free-text textarea with a mini syntax
- Severity: low
- Area: admin UI / i18n
- Where: Settings → Languages
- Actual: "One per line: code: name". Nothing validates the code against something known, nothing shows which language is main other than "the first line", and the help text tells a non-developer to "run php artisan sunrice:switch-main-language" to change the main language later.
- Expected: a small repeater (code, name, main) with validation; changing the main language either supported in the UI or explained without artisan.

### Site → title preview does not follow the separator
- Severity: polish
- Area: admin UI
- Where: Settings → Site → "Add the site name to titles"
- Actual: the hint always reads `"About | Acme"` even after changing the separator to "·" and the site name to "Lumina Estates".
- Expected: a live preview "About · Lumina Estates".
- Screenshot: qa/screenshots/admin-12-site-structured.jpg

### Structured data publisher has no name or contact details
- Severity: low
- Area: structure / site (SEO)
- Where: Settings → Site → Structured data
- Actual: "Published by: An organization / A person" and a logo only. There is no organization name (presumably the site name is used), URL, address, phone or social profiles (`sameAs`). For a real estate agency (schema.org `RealEstateAgent`, LocalBusiness) these matter for rich results.
- Expected: at least name and sameAs links, or a note saying the site name is used.

### Mail sender cannot be seen or fixed from the UI
- Severity: low
- Area: admin UI
- Where: Settings → Mail
- Actual: shows Mailer "log", From "Lumina Estates <hello@example.com>" read-only, "Mail is set up in the app's .env". The default `hello@example.com` sender is not flagged as a problem. The test email works and the page is honest about the log mailer ("Test email for ayu@… written to the log: the current mailer doesn't deliver email.", found in storage/logs/laravel.log with subject "Test email from Lumina Estates"). Good.
- Expected: a warning when the From address is still `hello@example.com`.
- Screenshot: qa/screenshots/admin-17-mail.jpg, qa/screenshots/admin-19-mail-test-sent.jpg

### Security settings: only one switch, help text points to artisan
- Severity: polish
- Area: admin UI
- Where: Settings → Security
- Actual: Only "Two-factor login" (email codes for everyone). No per-role enforcement, no TOTP app option, no password policy or session lifetime. The help text mentions `php artisan sunrice:two-factor off`, which a non-developer admin cannot run. Changing Site code sends a security alert email ("[Lumina Estates] Security: The site's code was changed"): nice touch.
- Screenshot: qa/screenshots/admin-20-security.jpg

## 4. Structure

**Order I was forced into** (the UI never tells you up front; you find out when a save fails or a list says "Nothing to choose from yet"):
1. Taxonomies (no collections to attach yet) and their terms.
2. Fieldsets that reference nothing (`contact_person`, `cta`, `hero`, `rich_text`, `image_text`, `stats`, `faq`).
3. Blueprints that reference nothing (`agent`, `neighbourhood`, `testimonial`, listing, global and form blueprints).
4. Collections `agents`, `neighbourhoods`, `testimonials`.
5. Blueprint `property` (entries → agents/neighbourhoods, terms → taxonomies), then collection `properties`.
6. Blueprint `post` (entries → agents/properties), then collection `journal`.
7. Fieldsets `featured_properties`, `team`, `testimonials` (entries → properties/agents/testimonials), then blueprint `page` (flexible blocks), then collection `pages`.
8. Back to every taxonomy to tick its collections.

### Handles cannot contain hyphens: the agreed content model cannot be built as written
- Severity: high
- Area: structure
- Where: every handle field (fieldsets, blueprints, taxonomies, collections, global sets, forms, menus)
- Steps: 1. Structure → Fieldsets → New fieldset. 2. Handle `contact-person`. 3. Create.
- Expected: hyphenated handles (`property-types`, `contact-person`, `rich-text`, `featured-properties`, `properties-listing`, `contact-form` …) accepted, as in the BRIEF, or converted.
- Actual: "The handle field format is invalid." (helper text does say "Lowercase letters, digits and underscores"). The auto-made handle from the title already uses underscores (`contact_person`).
- Note: I used underscore versions everywhere: `property_types`, `listing_types`, `contact_person`, `rich_text`, `image_text`, `featured_properties`, `properties_listing`, `journal_listing`, `contact_form`, `inquiry_form`, `valuation_form`, `newsletter_form`. **Developer and editor must use these.** Hyphens are common in URLs-style handles and in other CMSs; at least the error could suggest the underscore version.

### Reserved field handles (`status`, `author`, …) are only discovered on save
- Severity: medium
- Area: structure
- Where: Blueprint editor
- Steps: 1. Build the 23-field `property` blueprint with a select `status`. 2. Create blueprint.
- Expected: the handle box warns as soon as you type a reserved handle, and the Guide lists them.
- Actual: toast `"status" is used by the content itself; choose another handle.` after building the whole blueprint. Same for `author` in `post`. The reserved list (`id, title, slug, url, status, locale, published_at, created_at, updated_at, parent, children, collection, blueprint, data, seo, author`) is nowhere in the Guide or the form.
- Note: Used `property_status` instead of `status` (property) and `author_agent` instead of `author` (post). **Developer/editor: use these handles.** The BRIEF's model naturally contains `status` and `author`, which shows how easy it is to hit.

### A fieldset included in a blueprint is flattened: `company` + `contact_person` clash
- Severity: medium
- Area: structure
- Where: Blueprint `company`, field type Fieldset → Contact person
- Steps: 1. Blueprint with `phone`, `email` and a Fieldset field `primary_contact` → Contact person (which has `phone`, `email`). 2. Create.
- Expected: the include stored under its own handle (`primary_contact.phone`), as the label/handle boxes on the Fieldset field suggest.
- Actual: "There is already a field "phone" here." The Fieldset field's own handle is ignored: the fieldset's fields are pasted into the parent. Nothing in the UI says so (the field asks for a label and handle that do nothing).
- Note: Workaround: a Group `primary_contact` containing the Fieldset include → data shape `primary_contact.{name,role,phone,email}`. **Developer: read the company contact via the `primary_contact` group.**
- Screenshot: qa/screenshots/admin-43-bp-company-group-workaround.jpg

### "SEO from fields" lists fields of every blueprint, not the collection's
- Severity: medium
- Area: admin UI / structure
- Where: Collections → New/Edit → SEO from fields (Title, Description, Share image); Taxonomies too
- Steps: 1. New collection Neighbourhoods, blueprint Neighbourhood. 2. Open "Description".
- Expected: only `intro` (the Neighbourhood blueprint's text fields).
- Actual: About, Address, Client, Client role, Email, Intro, Legal name, Message, Name, Newsletter heading, Notes, Office hours, Phone, Property, Quote, Role, Tagline, WhatsApp — fields from all blueprints including forms and globals. Easy to pick a field the collection does not have; nothing warns.
- Screenshot: qa/screenshots/admin-47-seo-from-fields-all-blueprints.jpg

### Blueprint pickers offer every blueprint for every purpose
- Severity: low
- Area: admin UI
- Where: Global Sets → New (Blueprint), Forms → New (Blueprint), Collections → Listing page fields, Taxonomies → Term fields
- Actual: a global set can pick "Page", "Contact form" or "Property"; a form can pick "Agent". Blueprints have no "kind", so the lists get long and error-prone (15 blueprints already).
- Expected: blueprints tagged by use (entries / listing / terms / global / form), or at least the pickers grouping the ones already in use.

### Raw validation key when an Entries field has no collection
- Severity: medium
- Area: admin UI
- Where: Blueprint editor, Entries field, before any collection exists
- Steps: 1. New blueprint, add Entries field "Author". 2. "From collections: Nothing to choose from yet." 3. Create blueprint.
- Expected: "Choose at least one collection for Author. Create the Agents collection first (Structure → Collections)."
- Actual: toast "The fields.0.config.collections field is required." and no inline error on the field. "Nothing to choose from yet" does not say what to create; same for Flexible content blocks (needs fieldsets) and the Link field.
- Screenshot: qa/screenshots/admin-40-entries-field-no-collections.jpg

### Taxonomy must be attached twice (taxonomy → collections, and a Terms field in the blueprint)
- Severity: low
- Area: structure
- Where: Taxonomies → Collections, Blueprint → Terms field
- Actual: Adding a Terms field for `locations` to `property` does not attach Locations to Properties; you also tick Properties on the taxonomy. The collection form has no taxonomy setting at all, so from the collection's side you cannot see which taxonomies belong to it. The Guide mentions both steps, but it's duplicate configuration that can disagree.

### Taxonomy handle is not generated from the title (it is for fields and fieldsets)
- Severity: polish
- Area: admin UI
- Where: Taxonomies → New taxonomy (also Collections, Global sets, Menus, Forms)
- Actual: typing the Title leaves Handle empty; Fieldsets/Blueprints fill it in (as `contact_person`). Inconsistent.

### Handle mutability is inconsistent and the wording differs per screen
- Severity: low
- Area: admin UI / docs
- Where: Collections/Taxonomies/Forms ("can be changed later"), Blueprints/Fieldsets/Global sets/Menus ("It can't change later")
- Note: a typo in a blueprint, global set or menu handle can only be fixed by deleting and recreating it (and a blueprint in use can't be deleted). Worth a warning before Create.

### Field cards are all expanded; long blueprints become very long pages
- Severity: low
- Area: admin UI
- Where: Blueprint editor (`property`: 23 fields)
- Actual: every field opens expanded with all settings; no "collapse all"; the page for `property` is several thousand pixels tall (qa/screenshots/admin-48-bp-property.jpg). The placeholder of "Extra rules" (`min:3` / `regex:/^[A-Z]/`) looks like pre-filled rules on every field, and "Laravel rules, one per line" is developer language in an admin-facing screen.

### Flexible content blocks are stored alphabetically; block order can't be set
- Severity: polish
- Area: structure
- Where: Blueprint `page` → Sections → Kinds of block
- Actual: ticked blocks are saved sorted by handle (`cta, faq, featured_properties, hero, …`); there is no way to put Hero first in the editor's "Add block" menu.

### Form file field: single file only
- Severity: low
- Area: structure
- Where: Blueprint `valuation_form` → File (forms)
- Actual: options are "Accepted file types" and "Largest file (KB)"; no "several files". The valuation form's `photos` can take one photo only.

### Form thank-you message is one text for all languages
- Severity: medium
- Area: i18n
- Where: Forms → New/Edit → Thank-you message
- Actual: a single textarea. On a bilingual site the Indonesian visitor gets the English thank-you text (I saw no language switch on this screen).
- Expected: translatable like entry fields.

### Schema types are limited
- Severity: low
- Area: structure (SEO)
- Where: Collections → Structured data type
- Actual: Article, Blog post, News article, Web page, About page, Contact page. Nothing for people (agents → `Person`/`RealEstateAgent`), places or offers (properties → `Product`/`Offer`/`Residence`), which are the interesting ones for this site. Properties set to Web page as the BRIEF says.

### Developer-facing wording on admin forms
- Severity: polish
- Area: admin UI
- Where: Collections/Taxonomies → Template ("The Blade view … Empty: cms.{handle}, else cms.entry"), Forms → Handle ("How templates show it: <x-sunrice-cms::form handle="contact" />"), Forms → captcha ("Set SUNRICE_CAPTCHA_SITE_KEY …").
- Note: fine for a developer, puzzling for the site manager the Guide says these screens are for. Could be tucked under "For developers".

### Redirects work at once
- Severity: polish (positive)
- Area: site
- Note: `/listings` → 301 `/properties`, `/blog` → 301 `/journal` verified with curl. Listing and term routes (`/properties`, `/journal/topic/living`, `/location/kemang`) answer 200 straight away. `/team` currently answers 500 because the developer's `agents-listing.blade.php` reads `intro` from a listing blueprint the BRIEF does not define for agents (not a Sunrice bug; for the developer).

## 5. Menus

### Menus work well; page links have to wait for pages
- Severity: low
- Area: admin UI
- Where: Content → Menus → main / footer / legal
- Actual: Entry / Term / Listing / Link buttons, per-language labels ("Indonesian: as English"), In a level / Out a level, `javascript:` refused with a clear message and a disabled Add button. Term choices only list taxonomies with term pages (sensible). Saved `main` = Properties (listing) › For sale, For rent (links), Villas (term); Neighbourhoods, Team, Journal (listings). `footer` = four listings + mailto. `legal` stays empty: the Entry search says "No entries match." because no pages exist yet. Nothing in Menu settings or the Guide suggests "create pages first"; About, Contact, Privacy and Terms must be added later by whoever creates the pages.
- Screenshot: qa/screenshots/admin-62-menu-main-saved.jpg, qa/screenshots/admin-65-menu-legal-no-pages.jpg

### No listing-with-filter link type; the filter URL must be guessed
- Severity: low
- Area: admin UI / docs
- Where: Menus → Link
- Actual: "For sale / For rent" (BRIEF: "listing with filters via URL") have to be typed as plain links `/properties?listing_type=for-sale`. Neither the Guide nor the developer docs say what query parameters a listing page understands, so the admin guesses; the link also doesn't follow if the listing route changes and isn't translated to `/id/properties`.
- Note: the developer must confirm the parameter name or the links do nothing.

### Two places called "Menus"
- Severity: polish
- Area: admin UI
- Where: sidebar Content → Menus (`/admin/cms/menus`) and Structure → Menu settings (`/admin/menus`)
- Note: URLs are also inconsistent across the panel: `/admin/cms/entries`, `/admin/cms/blueprints`, `/admin/cms/fieldsets` versus `/admin/collections`, `/admin/taxonomies`, `/admin/forms`, `/admin/menus`.

## 6. Users and roles

### Scoped permissions per collection and per taxonomy exist and work
- Severity: polish (positive)
- Area: security / admin UI
- Where: Roles → New role
- Note: The role editor has "Entries: <collection>" (View, Create, Edit drafts, Publish and unpublish, Trash and restore, Export as CSV), "Terms: <taxonomy>" (Manage terms) and "Submissions: <form>" groups. "Listings manager" = Open the admin panel, Read the user guide, all four Media permissions, all six Entries: Properties, Terms: Locations, Terms: Amenities. Signed in as Eko: sidebar Dashboard, Entries, Terms, Media, Guide; `/admin/cms/collections/journal/entries` → friendly 403 "Not allowed"; `/admin/cms/taxonomies/topics/terms` → 403; settings/users/blueprints/activity → 403. Exactly as intended.
- Screenshot: qa/screenshots/admin-69-role-listings-manager.jpg, qa/screenshots/admin-72-role-eko-dashboard.jpg, qa/screenshots/admin-73-role-eko-forbidden-journal.jpg

### Built-in "author" role can write in every collection; the BRIEF's author is journal-only
- Severity: medium
- Area: security / structure
- Where: Roles → author
- Actual: the seeded author has global "View entries" and "Create entries", so Citra could create Pages, Properties, Agents… (drafts only, but still). There are per-collection View/Create, but **no per-collection "Edit drafts of their own entries" or "Trash their own entries"**, so the "own entries" model can only be global.
- Note: I edited the built-in author role: removed global View/Create entries, added Entries: Journal → View, Create; kept "Edit drafts of their own entries", "Trash their own entries", "See unpublished changes", media view/upload. Citra now sees only Dashboard, Entries (Journal), Media, Guide; Pages/Properties/Agents → 403. Editing a built-in role is a bit scary: nothing says whether a later update/seed will reset it.
- Screenshot: qa/screenshots/admin-70-role-author-scoped.jpg, qa/screenshots/admin-72-role-citra-dashboard.jpg

### Editor role can create and delete whole menus
- Severity: low
- Area: security
- Where: Roles → editor (seeded): `cms.menus.create`, `cms.menus.delete`
- Actual: Budi (editor) sees Structure → "Menu settings" and can delete the `main` menu the templates rely on. The Guide says "A site manager creates the menus; editors fill them in", which contradicts the seeded role.
- Screenshot: qa/screenshots/admin-72-role-budi-dashboard.jpg

### Role names show as slugs; custom role name keeps its spaces
- Severity: polish
- Area: admin UI
- Where: Roles list, Users list, role checkboxes in Users and Forms/Comments "Also email roles"
- Actual: `administrator`, `author`, `editor`, `super-admin`, `translator` next to "Listings manager". No display name vs. key, no description of what each built-in role is for in the list (the Guide explains them).

### Creating a user means typing their password; no invitation
- Severity: low
- Area: admin UI / security
- Where: Users → New user
- Actual: Name, Email, Password (required), Roles. No "send an invitation / set-your-password email", no "must change password at first sign-in". The admin has to pass `Lumina!Budi2026` etc. to people by some other channel. The Guide also says "There is no profile page", so users cannot change their own password except through "Forgot password?".

### Translator sees every collection, including ones with nothing to translate
- Severity: polish
- Area: admin UI
- Where: signed in as Dewi (translator)
- Actual: Entries lists Agents, Pages, Journal, Properties…; create is correctly 403. Fine, but there is no per-collection "translate" permission to limit a translator to, say, Journal and Pages.

### Eko's property form: not yet verifiable whether Agent/Neighbourhood pickers work for a role that can't see those collections
- Severity: low (open question)
- Area: security / editor UX
- Where: Eko → Properties → New entry
- Note: Agent and Neighbourhood render as empty fields (no agents exist yet). Worth testing once agents exist: Eko must be able to pick an agent without being allowed to open the Agents collection. (Flag for the editor tester.)
- Screenshot: qa/screenshots/admin-74-eko-property-create.jpg

## 7. AI access

### AI access page is clear; tokens created
- Severity: polish (positive)
- Area: admin UI / security
- Where: Administration → AI access
- Actual: New token → Name, "Read-only: the agent can look, but not change anything", Expires (30/90 days, a year, Never; default 90 days). The token is shown once with Copy and a ready-made `claude mcp add …` command and MCP JSON. Created "Ayu laptop – Claude (read-only)" and "Ayu laptop – Claude Code (full)", both expiring 18 Jan 2027. Verified against `/mcp`: read-only token lists 21 reading tools, full token 44 tools.
- Screenshot: qa/screenshots/admin-79-ai-tokens-list.jpg
- Note: qa/screenshots/admin-77-… and admin-78-… show the full token values (the page shows them in the connect command). Fine on this test box, but don't publish those screenshots.

### The page doesn't say what "full" means for a super admin
- Severity: low
- Area: security / docs
- Where: AI access → New token, Guide → AI access
- Actual: "work in the panel as you, with your permissions". For Ayu (super admin) a full token can change users, roles, Site code (scripts on every page), run "allowed commands" and delete records. The dialog could list the risky abilities of *this* account and suggest read-only or a shorter expiry. `cms_write_template` is advertised to the full token although `SUNRICE_AI_TEMPLATES` is off (it refuses when called: safe, but confusing for the agent).

## 8. Activity log

### Activity log records everything, but details are raw keys and there is no "who" filter
- Severity: low
- Area: admin UI
- Where: Administration → Activity log
- Actual: All my structure work is there (created blueprint/collection/taxonomy/term/menu…, updated settings, roles, AI tokens), plus security events (login failed, page refused for Eko, "alert sent: Someone keeps opening pages they may not see", "site code changed"). Filters: source (Admin / AI agent / System / Security), type, action, date range, page size. But:
  - Details list internal keys only: `seo_description_field, seo_image_field`, `schema_type`, `closeAfterDays`, `permissions`. No before → after values, and menu updates show no details at all. For an audit trail "permissions" without which permissions were added/removed is weak.
  - The type filter lists "Menu" twice (menus and menu items, indistinguishable).
  - There is no "Who" filter in the UI, although the dashboard links use `?subject=1`.
  - Sentence form is awkward: "Ayu Pratiwi logged in Ayu Pratiwi", "Someone login failed Ayu Pratiwi".
- Screenshot: qa/screenshots/admin-80-activity.jpg, qa/screenshots/admin-81-activity-filtered.jpg, qa/screenshots/admin-82-activity-updated.jpg

## 9. Dashboard

### Dashboard after setup: sensible, but no "what's next" and activity is security noise
- Severity: low
- Area: admin UI
- Where: /admin
- Actual: Content lists the six collections with "0 live / No drafts"; Comments waiting, Coming up, Latest submissions, Media (0) and Recent activity. After an hour of structure work:
  - "Recently edited: Nothing has been edited yet." (it only counts entries, which reads wrong after creating 15 blueprints).
  - Recent activity is all security lines ("alert sent", "page refused", "token created") with the subject duplicated ("Eko Wibowo page refused Eko Wibowo").
  - Nothing tells the admin what is still missing for the site to work: no homepage set (Settings → Site), no pages, `legal` menu empty, mail is the log mailer, global sets empty. A short setup checklist would fit the "non-developer admin" story.
- Screenshot: qa/screenshots/admin-83-dashboard-after-setup.jpg

## 10. Two-factor login

### Security switch shows "on" after turning two-factor OFF (stale form after save)
- Severity: high
- Area: admin UI / security
- Where: Settings → Security → Two-factor login
- Steps: 1. With two-factor on, open Settings → Security. 2. Switch it off. 3. Save ("Security settings saved."). 4. Look at the switch.
- Expected: switch off.
- Actual: the switch springs back to **on** while the database holds `{"twoFactor":false}` (and the "Two-factor login was turned off" alert email is sent). After a reload it shows off. An admin checking their work sees 2FA enabled when it is disabled. Same root cause as the Comments number field above: settings pages re-render with the values from before the save.
- Screenshot: qa/screenshots/admin-88-2fa-off.jpg
- Note: Verified by reload and DB. **2FA is OFF at the end of my session** (so the other testers can sign in), recorded here as asked.

### Two-factor sign-in flow works well
- Severity: polish (positive)
- Area: security
- Where: /admin/login → /admin/login/code
- Actual: after the password: "Check your email. We sent a 6-digit code to a••@lumina-estates.test. It works for 10 minutes." with "Send a new code in 55s" and "Back to log in". Wrong code: "That code isn't right. Check the latest email and try again." The right code (from storage/logs/laravel.log) signs in. Turning it off emails a security alert.
- Screenshot: qa/screenshots/admin-85-2fa-code-screen.jpg, qa/screenshots/admin-86-2fa-wrong-code.jpg, qa/screenshots/admin-87-2fa-signed-in.jpg

### Login code is in the email subject
- Severity: low
- Area: security
- Where: login code email
- Actual: Subject "Your Lumina Estates login code: 529709". The code shows in phone lock-screen notifications and mail previews. Common practice, but worth a config option.

### Turning on two-factor while the mailer is "log" is allowed without a warning
- Severity: medium
- Area: security / admin UI
- Where: Settings → Security
- Steps: 1. Mail page says "The log mailer doesn't deliver email … Nobody receives login codes". 2. Turn on Two-factor login, save.
- Expected: a warning or confirmation ("Mail isn't delivered: everyone will be locked out") since the panel already knows the mailer is `log`.
- Actual: saved silently. On a real site this locks everybody out; the way back is `php artisan sunrice:two-factor off`, which a non-developer admin can't run.

## 11. Help → Guide

The Guide is genuinely good: plain language, task-oriented, Indonesian version, "For developers" boxes kept apart. Inaccuracies and gaps I hit:

### Guide → Structure misses the things that actually blocked me
- Severity: medium
- Area: docs
- Where: /admin/guide/website/structure
- Actual: it doesn't mention (1) the order things must be created in (blueprints before collections; collections before an Entries field can point at them; fieldsets before Flexible content; taxonomies before Terms fields), (2) the reserved field handles (`status`, `author`, `title`, `slug`, …), (3) that a Fieldset field is pasted into the parent and its own handle ignored (so its fields must not clash), (4) that hyphens are not allowed in any handle (only said for collections). A one-paragraph "Setting up a new site, in order" would have saved most of my trial and error.

### Guide wording doesn't match the screens in a few places
- Severity: low
- Area: docs
- Where: Guide pages vs. screens
- Actual:
  - Structure → Collections: "Manual order: Editors put entries in order (Reorder in the list)" – the form says "(Move up, Move down)".
  - Website settings → Languages: "The panel refuses an empty list" – but the page opens with an empty box on a site that already has en and id (placeholder only), so saving the page untouched would be refused.
  - Welcome: "There is no profile page: to change your name or email address, ask a site manager" vs. Users and roles: "Everyone may edit their own name, email and password if they can open the Users screen". Both true, but contradictory at first read; neither says how an editor changes their own password (only "Forgot password?").
  - Users and roles: describes the editor as "Not structure", but the seeded editor role has Menu settings (create/delete menus).
  - Settings → Security: the screen and the Guide point non-developers to `php artisan sunrice:two-factor off`; the Guide at least puts it in a "For developers" box, the screen does not.
  - The Guide's Settings and Website-settings pages both describe "Add the site name to titles" with `"About | Acme"`; fine, but the screen's preview doesn't update with the real separator/name.

### Guide → Appearance explains the reload (good), Activity log page matches the screen
- Severity: polish (positive)
- Area: docs
- Note: Activity log Guide (Search, filters, history icon, person filter by clicking the name, security alerts) matches the screen; Menus and AI access Guides are accurate.

---

## Setup summary (what exists now)

All created through the admin UI. Handles that differ from the BRIEF are **bold**.

- **Settings**: Branding (Lumina Estates / "Homes of quiet distinction", Manrope, #0F4C4C, sign-in copy, side-panel layout, no panel logo). Languages `en: English`, `id: Bahasa Indonesia`. Site: name Lumina Estates, description, separator "·", structured data on, publisher Organization, logo = lumina-logo.svg; homepage not set (no pages yet). Comments: hold first comment, 3/min, close after 365 days, 3 reply levels, notify moderators, reply emails on. Site code: head placeholder comment. Security: **two-factor OFF** (tested on, then turned off). Mail: log mailer, test email sent.
- **Taxonomies**: **`property_types`** (/property-type/{slug}; House, Villa, Apartment, Land, Commercial), **`listing_types`** (For sale, For rent), `locations` (nested, /location/{slug}; Jakarta › South Jakarta › Kemang, Pondok Indah; Jakarta › Central Jakarta › Menteng; Bali › Badung › Canggu, Seminyak; Bali › Gianyar › Ubud), `amenities` (10 terms), `topics` "Journal topics" (/journal/topic/{slug}; 4 terms). Attached: properties ← property_types, listing_types, locations, amenities; journal ← topics. Term names English only.
- **Fieldsets**: **`contact_person`** (name, role, phone, email), `cta` "Call to action" (heading, text, button), `hero`, **`rich_text`**, **`image_text`**, `stats`, `faq`, **`featured_properties`** (max 6), `team`, `testimonials`.
- **Blueprints**: `page` (intro, sections flexible: cta, faq, featured_properties, hero, image_text, rich_text, stats, team, testimonials), `property` (all BRIEF fields; **`property_status`** instead of `status`; shared/translatable as BRIEF; gallery images max 12; floor_plan images+documents), `agent`, `neighbourhood`, `post` (**`author_agent`** instead of `author`; related max 3), `testimonial` (rating 1–5), **`properties_listing`**, **`journal_listing`**, `company` (contact via **group `primary_contact` containing the contact_person fieldset**), `social`, `footer`, **`contact_form`**, **`inquiry_form`**, **`valuation_form`** (photos: single file, jpg/jpeg/png/webp, 5120 KB), **`newsletter_form`**.
- **Collections**: `pages` (/{slug}, hierarchical, ordered, SEO description←intro), `properties` (/properties/{slug}, listing /properties with properties_listing, 9 per page, Web page, SEO description←summary, image←gallery), `agents` (/team/{slug}, listing /team, ordered), `neighbourhoods` (/neighbourhoods/{slug}, listing /neighbourhoods, SEO ← intro/hero), `journal` (/journal/{slug}, listing /journal with journal_listing, 6 per page, dated, comments, Blog post, SEO ← excerpt/cover), `testimonials` (no URL, ordered).
- **Global sets**: `company`, `social`, `footer` (empty values).
- **Forms**: `contact` (contact_form, email ayu@…, thank-you text), `inquiry` (inquiry_form, ayu@…), `valuation` (valuation_form, ayu@…, 3 per minute, captcha off), `newsletter` (newsletter_form, no email). All with a thank-you message, no redirect.
- **Menus**: `main` (Properties › For sale, For rent [links `/properties?listing_type=…`], Villas [term]; Neighbourhoods; Team; Journal) – About and Contact still to add; `footer` (Properties, Neighbourhoods, Our team, Journal, mailto) ; `legal` empty (Privacy/Terms pages don't exist yet).
- **Redirects**: `/listings` → `/properties` 301, `/blog` → `/journal` 301 (verified).
- **Roles/users**: built-in author edited to Journal-only; new role "Listings manager". Users Budi (editor), Citra (author), Dewi (translator), Eko (Listings manager), passwords `Lumina!<FirstName>2026`.
- **AI tokens** for Ayu: "Ayu laptop – Claude (read-only)" and "Ayu laptop – Claude Code (full)", 90 days.
- **Media**: lumina-logo.svg (test PNG uploaded and deleted for good).

## What worked well

- Calm, consistent UI with good empty states, friendly 403/404 pages and clear toasts; inline validation where it exists.
- Scoped permissions per collection/taxonomy/form, and they hold when probing URLs directly; refused pages are logged and alerted.
- Security touches: 2FA by email with masked address and resend timer, alert emails for site code changes, AI tokens and 2FA off, `javascript:` links refused in menus, AI tokens read-only by default option and expiry.
- Nested terms with an "Under" picker and drag handles; menu editor with per-language labels and In/Out a level.
- Mail page that honestly says the log mailer delivers nothing; the test email works.
- Redirects, listing routes and term routes work the moment they are saved.
- The Guide (and its Indonesian version) is readable for a non-developer, much better than most CMSs.
