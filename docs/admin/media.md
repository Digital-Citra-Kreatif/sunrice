# Media

The panel has one media library, shared by your app and every plugin: files on a Laravel disk, a `Sunrice\Admin\Media\Media` record for each, in folders (`MediaFolder`), with smaller copies of images.

## The Media page

**Media** in the sidebar: folders, a grid of files (thumbnails for images), search across every folder, a filter by type, and the trash. Drop files anywhere on the page (or use Upload) to add them to the folder you're in; each file uploads on its own, with its progress and any error. Click a file for its preview and details: title, alternative text, caption, copy its link, or replace its file (the link stays the same). Select files to move them to another folder or to the trash; in the trash, restore them or delete them for good. Deleting a folder moves what's in it up a level, so nothing is lost.

| Permission | Allows |
| --- | --- |
| `sunrice.media.view` | Seeing the library |
| `sunrice.media.upload` | Uploading |
| `sunrice.media.update` | Editing details, replacing files, folders and moving |
| `sunrice.media.delete` | The trash: moving to it, restoring, deleting for good |

When the media disk is a local disk served through a link in `filesystems.links` that doesn't exist (`php artisan storage:link` wasn't run), the page tells those who may upload, since files' links lead nowhere until it is.

Turn the page off with `$panel->mediaLibrary(false)`; the library still works from code.

## Where files are used

A file's details list where it's **used by** (with links), as the plugins using it report it: the CMS reports entries and global sets. Moving a file in use to the trash asks first; deleting it for good asks too, and selecting several to delete keeps those in use (open each to see where). `DeleteMedia::handle($media, force: true)` deletes one anyway.

Report your own items' files when they're saved, and forget them when they're deleted (a type starting with your plugin's id, its id, the files, a label and a link):

```php
use Sunrice\Admin\Media\MediaUsages;

app(MediaUsages::class)->sync('shop.product', (string) $product->id, [$product->image_id, ...$product->gallery], "Product: {$product->name}", route('sunrice.admin.resources.products.edit', $product));
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
use Sunrice\Admin\Actions\Media\{UploadMedia, ReplaceMedia, DeleteMedia, MoveMedia, UpdateMedia, SaveMediaFolder, DeleteMediaFolder};

$media = app(UploadMedia::class)->handle($request->file('photo'), ['folder_id' => $folder->id, 'alt' => 'The shop front']);

$media->url();               // the original
$media->url('thumbnail');    // a size (the original when it has none)
$media->thumbnail();         // the "thumbnail" size, else the smallest size it has, else the original
$media->srcset();            // "…-medium.jpg?v=1 800w, …-large.jpg?v=1 1600w, ….jpg?v=1 2400w"
$media->srcset('thumbnail', 'medium');   // only these sizes (and the original)

app(ReplaceMedia::class)->handle($media, $newFile);   // same URL, new version
$media->delete();                                      // to the trash (soft delete)
app(DeleteMedia::class)->handle($media);               // gone, with its files

app(UpdateMedia::class)->handle($media, ['alt' => 'The shop front', 'caption' => null]);
$folder = app(SaveMediaFolder::class)->handle('Products');          // or (name, parent), or rename: (name, folder: $folder)
app(MoveMedia::class)->handle([$media], $folder);                    // null: the top level
app(DeleteMediaFolder::class)->handle($folder);                      // its contents move up
```

URLs end in `?v=<version>`, so a replaced file is fetched again rather than served from a cache.

`srcset()` gives the value of an `<img srcset>`: each size's URL with its width, worked out from the size's settings and the original's dimensions, and the original's. Without names it uses the sizes that keep the picture's proportions (`fit`, not `crop`); sizes as wide as the original, or as one already listed, are left out, and files that can't have sizes (SVGs, documents) give `''`:

```blade
<img src="{{ $media->url('medium') }}" srcset="{{ $media->srcset() }}" sizes="(min-width: 50rem) 50rem, 100vw" alt="{{ $media->alt }}">
```

## Settings

In `config/sunrice-admin.php` under `media`:

| Key | Default | |
| --- | --- | --- |
| `disk` / `directory` | `public` / `media` | Where files go: `{directory}/{Y}/{m}/{random}-{name}.{ext}`. The `public` disk needs `php artisan storage:link`. |
| `max_upload_kb` | `20480` | The largest upload; PHP's `upload_max_filesize` and `post_max_size` cap it too. |
| `allowed_extensions` | images, documents, audio, video, fonts | What may be uploaded. |
| `image_sizes` | `thumbnail` 300×300 crop, `medium` 800 wide, `large` 1600 wide | `name => [width, height, 'crop' or 'fit']`. `fit` scales down only; `null` leaves a side free. |
| `queue` | `null` | Make sizes on the queue instead of during the upload: `true`, `false`, or `null` to queue them whenever the default queue connection isn't `sync` (or `null`). A queued image keeps only its original until the job ran: `url('thumbnail')` and the library's thumbnails show the original meanwhile. |
| `max_pixels` | 40 million | Larger images keep only their original (decoding them could run out of memory). |
| `optimize` | 2560 × 2560, quality 82, backups on `local` | Defaults of `sunrice:optimize-images` (see above). |

After changing `image_sizes`, run `php artisan sunrice:media-sizes` (or `--id=12 --id=15` for some): it makes the new sizes and deletes ones that are gone. Sizes need PHP's GD or Imagick extension (Imagick is used when present); without one, or for a file it can't read, media keeps only its original.

## What's refused

Media is served from your app's own domain, so a file that runs code there could act as the people signed in to it. Every upload is checked:

- the extension must be allowed, and scripts, executables and web pages (`.php`, `.html`, `.js`, `.svgz`, `.xml` …) are refused whatever the list says;
- the content is checked too: a web page or script named `notes.txt` is refused;
- SVGs must be free of scripts, event handlers, `javascript:` links, embedded documents and entities. They're refused rather than cleaned, so nothing is changed silently; logos and icons from design tools pass;
- files over the size limit are refused with a clear message.

Stored names are new and random, never the uploader's.

### Headers at the web server

The `allowed_extensions` list includes files that aren't images, such as `json`, `txt`, `csv` and `zip`, and a file's content is checked only as far as its type can be: an image can carry text a browser might read as something else if it guessed. The public disk is served by your web server, not by PHP, so set these headers there for the media directory (`storage/media` with the defaults):

- `X-Content-Type-Options: nosniff` on everything, so browsers use the declared type and never guess;
- `Content-Disposition: attachment` for everything but images (and video and audio, if you embed them), so documents and archives are downloaded rather than opened on your domain.

For nginx, for example:

```nginx
location /storage/media/ {
    add_header X-Content-Type-Options nosniff always;
    location ~* \.(json|txt|csv|zip|pdf|docx?|xlsx?|pptx?)$ {
        add_header X-Content-Type-Options nosniff always;
        add_header Content-Disposition attachment always;
    }
}
```
