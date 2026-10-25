# Media

The panel has one media library, shared by your app and every plugin: files on a Laravel disk, a `Sunrice\Admin\Media\Media` record for each, in folders (`MediaFolder`), with smaller copies of images.

## The Media page

**Media** in the sidebar: folders, a grid of files (thumbnails for images), search across every folder, a filter by type, and the trash. Drop files anywhere on the page (or use Upload) to add them to the folder you're in; each file uploads on its own, with its progress and any error. Click a file for its preview and details: title, alternative text, caption (and both in each of the content's other languages, when a plugin gives them: see below), copy its link, or replace its file (the link stays the same). The path above the files (All media › Photos › …) shows where you are. Drag files onto a folder, or onto a step of the path, to move them there (dragging a selected file moves every selected one), or select them and use **Move**; select files to put them in the trash too; in the trash, restore them or delete them permanently. Deleting a folder moves what's in it up a level, so nothing is lost; a folder whose name is taken there gets a number ("Photos (2)").

| Permission | Allows |
| --- | --- |
| `sunrice.media.view` | Seeing the library |
| `sunrice.media.upload` | Uploading |
| `sunrice.media.update` | Editing details, replacing files, folders and moving |
| `sunrice.media.delete` | The trash: moving to it, restoring, permanently deleting |

### The trash

**Move to trash** takes a file offline: its original and all its sizes move off the media disk to a private disk (`media.trash_disk`, the `local` disk by default, in `storage/app/private`) under `media.trash_directory` (`sunrice-media-trash/`), at the same path, so its links answer 404 at once (an annual report uploaded by mistake is gone from `/storage/media/…`, while it can still be restored). **Restore** moves them back to the same paths, so links work again; **Permanently delete** deletes them from wherever they are, the trash or the media disk. A move is all or nothing: every file is copied first and the originals are deleted only once all copies are there; if one can't be moved, what was done is undone, the file stays where it was and the page says so (`MediaFilesNotMoved` from code). In the trash a file shows a placeholder instead of its preview and has no link to copy. Image sizes still being made on the queue for a file moved to the trash meanwhile are deleted again, not left on the public disk.

Sites upgraded from 0.1 still have the files of media they trashed before on the public disk: `php artisan sunrice:media-trash-offline` moves them (once; it leaves files already moved alone).

When the media disk is a local disk served through a link in `filesystems.links` that doesn't exist (`php artisan storage:link` wasn't run), the page tells those who may upload, since files' links lead nowhere until it is.

Turn the page off with `$panel->mediaLibrary(false)`; the library still works from code.

## Where files are used

A file's details list where it's **used by** (with links), as the plugins using it report it: the CMS reports entries and global sets, and the panel reports what a settings section's media pickers hold whenever it's saved, changed or not (Branding's logo, browser icon and sign-in image; Settings → Site's share image and logo; your own sections' pickers; `php artisan migrate` recorded those of sections saved before). Moving a file in use to the trash asks first; deleting it permanently asks too (and warns that pages using it will show a broken link; a file nothing uses just says so), and selecting several to delete keeps those in use (open each to see where). `DeleteMedia::handle($media, force: true)` deletes one anyway.

Report your own items' files when they're saved, and forget them when they're deleted (a type starting with your plugin's id, its id, the files, a label and a link):

```php
use Sunrice\Admin\Media\MediaUsages;

app(MediaUsages::class)->sync('shop.product', (string) $product->id, [$product->image_id, ...$product->gallery], "Product: {$product->name}", route('sunrice.admin.shop.products.edit', $product));
app(MediaUsages::class)->forget('shop.product', (string) $product->id);
app(MediaUsages::class)->of($media); // [['type' => …, 'label' => …, 'url' => …], …]
```

## Optimizing images

`php artisan sunrice:optimize-images` scales down images larger than `optimize.max_width` × `max_height` (2560) and compresses JPEG, WebP and AVIF at `quality` (82), in place: files keep their paths, so links don't change; the version goes up (browsers fetch them again) and their sizes are made again. GIFs (animations) and SVGs are left alone, and an image that already fits is only written when it gets smaller.

```bash
php artisan sunrice:optimize-images                      # the config's defaults
php artisan sunrice:optimize-images --max-width=1920 --quality=75 --id=12
php artisan sunrice:optimize-images --dry-run            # report only
php artisan sunrice:optimize-images --restore            # put the originals back
```

The first original of each image is kept on `optimize.backup_disk` (the private `local` disk, so it's not reachable by URL) under `backup_directory`; later runs never overwrite it, `--restore` puts it back, and replacing or deleting the file deletes it. `--no-backup` (or `backup => false`) keeps no originals. In code: `OptimizeImage` and `RestoreOriginalImage`.

## Picking media in forms

`MediaPicker` lets people choose files from the library, or upload new ones, in [resource](resources.md) and [settings](settings.md) forms:

```php
use Sunrice\Admin\Resources\Forms\MediaPicker;

MediaPicker::make('image_id')->images();                   // stores a media id (or null)
MediaPicker::make('gallery')->images()->multiple(8);       // stores a list of ids, in order
MediaPicker::make('brochure')->types(['document']);
```

- One file stores its id; `->multiple()` stores a list (cast the attribute to `array`, or use an `array` settings property). `->multiple(8)` allows at most 8.
- `->images()` or `->types([...])` (image, video, audio, document) limit what can be chosen. The server accepts only existing media of those types, never something in the trash.
- Choosing needs `sunrice.media.view`; uploading from the picker needs `sunrice.media.upload`.

Read it back with the model: `Media::find($product->image_id)?->url('medium')`.

## Uploading from code

The page and the picker post each file to `POST /admin/media` (`file`, an optional `folder_id`, and optionally a `title` and `alt` to set with the file; empty ones mean the file's name and no alternative text). In PHP:

```php
use Sunrice\Admin\Actions\Media\{UploadMedia, ReplaceMedia, TrashMedia, DeleteMedia, MoveMedia, UpdateMedia, SaveMediaFolder, DeleteMediaFolder};

$media = app(UploadMedia::class)->handle($request->file('photo'), ['folder_id' => $folder->id, 'alt' => 'The shop front']);

$media->url();               // the original
$media->url('thumbnail');    // a size (the original when it has none)
$media->thumbnail();         // the "thumbnail" size, else the smallest size it has, else the original
$media->srcset();            // "…-medium.jpg?v=1 800w, …-large.jpg?v=1 1600w, ….jpg?v=1 2400w"
$media->srcset('thumbnail', 'medium');   // only these sizes (and the original)

app(ReplaceMedia::class)->handle($media, $newFile);   // same URL, new version
app(TrashMedia::class)->handle($media);                // to the trash: its files go off the public disk ($media->delete() too, not a query's ->delete())
app(TrashMedia::class)->restore($media);               // back, files and all
app(DeleteMedia::class)->handle($media);               // gone, with its files, wherever they are

app(UpdateMedia::class)->handle($media, ['alt' => 'The shop front', 'caption' => null]);
app(UpdateMedia::class)->handle($media, ['translations' => ['id' => ['alt' => 'Bagian depan toko']]]);
$folder = app(SaveMediaFolder::class)->handle('Products');          // or (name, parent), or rename: (name, folder: $folder)
app(MoveMedia::class)->handle([$media], $folder);                    // null: the top level
app(DeleteMediaFolder::class)->handle($folder);                      // its contents move up
```

URLs end in `?v=<version>`, so a replaced file is fetched again rather than served from a cache.

`srcset()` gives the value of an `<img srcset>`: each size's URL with its width, worked out from the size's settings and the original's dimensions, and the original's. Without names it uses the sizes that keep the picture's proportions (`fit`, not `crop`); sizes as wide as the original, or as one already listed, are left out, and files that can't have sizes (SVGs, documents) give `''`:

```blade
<img src="{{ $media->url('medium') }}" srcset="{{ $media->srcset() }}" sizes="(min-width: 50rem) 50rem, 100vw" alt="{{ $media->altIn() }}">
```

**As data for a script** (a gallery, a lightbox) or a JSON route: `$media->toData()` (`@api`) gives a plain array with the same URLs as `url()` and `srcset()`, and the alt text and caption in a language (`toData('id')`; default: the app's, as `altIn()`):

```json
{"id": 12, "url": "/storage/media/2026/10/ab12-shop.jpg?v=1", "alt": "The shop front", "title": "Shop", "caption": null,
 "mime": "image/jpeg", "type": "image", "width": 2400, "height": 1200,
 "sizes": {"thumbnail": {"url": "…-thumbnail.jpg?v=1", "width": 300, "height": 300}, "medium": {"url": "…-medium.jpg?v=1", "width": 800, "height": 400}},
 "srcset": "…-medium.jpg?v=1 800w, …-large.jpg?v=1 1600w, ….jpg?v=1 2400w"}
```

`type` is image, video, audio or document (as `->types()` counts them); `width` and `height` are an image's (null for other files and for images whose size isn't known); `sizes` are the sizes it has (a queued image has none until its job ran), each one's width and height worked out as for `srcset()` (null when unknown). A file in the trash has `url: null`, no sizes and an empty `srcset`, since its files are offline. Only these keys: never its disk, path, who uploaded it or its other languages' text. `toArray()` and `toJson()` are Eloquent's own, with all of those; don't print them on a page. The CMS gives its entries' media fields this way ([Data for JavaScript](../../cms/docs/site.md#data-for-javascript)).

**Alternative text and captions per language.** A plugin with content in several languages (the CMS) gives the panel its languages, main first: `$panel->contentLocales(fn (): array => ['en' => 'English', 'id' => 'Indonesian'])` (`@api`; `getContentLocales()` reads them). Each file then takes its alt text and caption in the others (`Media::$translations`, `{"id": {"alt": "…", "caption": "…"}}`). `$media->altIn($locale)` and `captionIn($locale)` give them in a language (default: the app's locale, which the CMS sets to the page's), else the file's own: write `alt="{{ $media->altIn() }}"` in templates.

## Settings

In `config/sunrice-admin.php` under `media`:

| Key | Default | |
| --- | --- | --- |
| `disk` / `directory` | `public` / `media` | Where files go: `{directory}/{Y}/{m}/{random}-{name}.{ext}`. The `public` disk needs `php artisan storage:link`. |
| `trash_disk` / `trash_directory` | `local` / `sunrice-media-trash` | Where files in the trash are kept, at their own paths under the directory: a disk that's never served by URL (see [The trash](#the-trash)). |
| `max_upload_kb` | `20480` | The largest upload; PHP's `upload_max_filesize` and `post_max_size` cap it too. |
| `allowed_extensions` | images, documents, audio, video, fonts | What may be uploaded. |
| `image_sizes` | `thumbnail` 300×300 crop, `medium` 800 wide, `large` 1600 wide | `name => [width, height, 'crop' or 'fit']`. `fit` scales down only; `null` leaves a side free. |
| `queue` | `null` | Make sizes on the queue instead of during the upload: `true`, `false`, or `null` to queue them whenever the default queue connection isn't `sync` (or `null`). A queued image keeps only its original until the job ran: `url('thumbnail')` and the library's thumbnails show the original meanwhile. An upload inside a database transaction queues its job once that commits. |
| `max_pixels` | 40 million | Larger images keep only their original (decoding them could run out of memory). |
| `optimize` | 2560 × 2560, quality 82, backups on `local` | Defaults of `sunrice:optimize-images` (see above). |

After changing `image_sizes`, run `php artisan sunrice:media-sizes` (or `--id=12 --id=15` for some): it makes the new sizes and deletes ones that are gone. Sizes need PHP's GD or Imagick extension (Imagick is used when present); without one, or for a file it can't read, media keeps only its original.

## What's refused

Media is served from your app's own domain, so a file that runs code there could act as the people signed in to it. Every upload is checked:

- the extension must be allowed, and scripts, executables and web pages (`.php`, `.html`, `.js`, `.svgz`, `.xml` …) are refused whatever the list says;
- the content is checked too: a web page or script named `notes.txt` is refused; a file named as an image (`.jpg`, `.png`, `.gif`, `.webp`, `.avif`, `.bmp`) must be a readable image ("This isn't a valid image"), and one with PHP code in it (an image followed by `<?php …`, by the short echo tag `<?= … ?>` (whatever bytes its comments or strings hold; the code is read with PHP's tokenizer, so those bytes in a photo's data don't count), or, where `short_open_tag` is on, by `<? …`) is refused and logged. Other code can check files the same way with `Sunrice\Admin\Media\FileContent` (`@api`): `matchesExtension($file)`, `containsPhp($file)`;
- SVGs must be free of scripts, event handlers, `javascript:` links, embedded documents and entities. They're refused rather than cleaned, so nothing is changed silently; logos and icons from design tools pass;
- files over the size limit are refused with a clear message.

Stored names are new and random, never the uploader's.

### Headers at the web server

The `allowed_extensions` list includes files that aren't images, such as `json`, `txt`, `csv` and `zip`, and a file's content is checked only as far as its type can be: an image can carry text a browser might read as something else if it guessed, and an SVG opened by itself (not in an `<img>`) is a document on your domain. The public disk is served by your web server, not by PHP, so the panel can't add headers to it: set these there for the media directory (`storage/media` with the defaults):

- `X-Content-Type-Options: nosniff` on everything, so browsers use the declared type and never guess;
- for `.svg`, `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:` and `Content-Disposition: inline`, so an SVG opened by itself can't run a script or load anything, even if one got past the upload check;
- `Content-Disposition: attachment` for everything but images (and video and audio, if you embed them), so documents and archives are downloaded rather than opened on your domain.

**nginx** (in the `server` block; `add_header` in a nested `location` replaces the outer ones, hence the repeats):

```nginx
location ^~ /storage/media/ {
    add_header X-Content-Type-Options "nosniff" always;

    location ~* \.svg$ {
        add_header X-Content-Type-Options "nosniff" always;
        add_header Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:" always;
        add_header Content-Disposition "inline" always;
    }
    location ~* \.(json|txt|csv|zip|pdf|docx?|xlsx?|pptx?|odt|ods|odp|rtf)$ {
        add_header X-Content-Type-Options "nosniff" always;
        add_header Content-Disposition "attachment" always;
    }
}
```

**Apache**: `php artisan sunrice:install` writes this as `.htaccess` in the media directory when the media disk is a local one and the file isn't there yet (it's never overwritten, so change it as you like; other web servers ignore it). It needs `mod_headers` and `AllowOverride FileInfo`, as Laravel's own `public/.htaccess` does. In a virtual host instead, put it in `<Directory "/path/to/app/storage/app/public/media">`:

```apache
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    <FilesMatch "(?i)\.svg$">
        Header always set Content-Security-Policy "default-src 'none'; style-src 'unsafe-inline'; img-src data:"
        Header always set Content-Disposition "inline"
    </FilesMatch>
    <FilesMatch "(?i)\.(json|txt|csv|zip|pdf|docx?|xlsx?|pptx?|odt|ods|odp|rtf)$">
        Header always set Content-Disposition "attachment"
    </FilesMatch>
</IfModule>
```

Check with `curl -I https://your-app.example/storage/media/…/logo.svg`. **Stronger still:** serve media from an origin of its own (`media.your-app.example`, a CDN, or an S3 bucket's domain: set the disk's `url`), so a file there can never act on your app's domain, whatever its headers. The panel serves no media through PHP; the Help pages' images it does serve are PNG, JPEG, GIF, WebP or AVIF only, with `nosniff`.
