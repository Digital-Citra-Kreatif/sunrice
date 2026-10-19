# Media

The panel has one media library, shared by your app and every plugin: files on a Laravel disk, a `Sunrice\Admin\Media\Media` record for each, in folders (`MediaFolder`), with smaller copies of images.

## Uploading from code

```php
use Sunrice\Admin\Actions\Media\{UploadMedia, ReplaceMedia, DeleteMedia};

$media = app(UploadMedia::class)->handle($request->file('photo'), ['folder_id' => $folder->id, 'alt' => 'The shop front']);

$media->url();               // the original
$media->url('thumbnail');    // a size (the original when it has none)

app(ReplaceMedia::class)->handle($media, $newFile);   // same URL, new version
$media->delete();                                      // to the trash (soft delete)
app(DeleteMedia::class)->handle($media);               // gone, with its files
```

URLs end in `?v=<version>`, so a replaced file is fetched again rather than served from a cache.

## Settings

In `config/sunrice-admin.php` under `media`:

| Key | Default | |
| --- | --- | --- |
| `disk` / `directory` | `public` / `media` | Where files go: `{directory}/{Y}/{m}/{random}-{name}.{ext}`. The `public` disk needs `php artisan storage:link`. |
| `max_upload_kb` | `20480` | The largest upload; PHP's `upload_max_filesize` and `post_max_size` cap it too. |
| `allowed_extensions` | images, documents, audio, video, fonts | What may be uploaded. |
| `image_sizes` | `thumbnail` 300×300 crop, `medium` 800 wide, `large` 1600 wide | `name => [width, height, 'crop' or 'fit']`. `fit` scales down only; `null` leaves a side free. |
| `queue` | `false` | Make sizes on the queue instead of during the upload. |
| `max_pixels` | 40 million | Larger images keep only their original (decoding them could run out of memory). |

After changing `image_sizes`, run `php artisan sunrice:media-sizes` (or `--id=12 --id=15` for some): it makes the new sizes and deletes ones that are gone. Sizes need PHP's GD or Imagick extension (Imagick is used when present); without one, or for a file it can't read, media keeps only its original.

## What's refused

Media is served from your app's own domain, so a file that runs code there could act as the people signed in to it. Every upload is checked:

- the extension must be allowed, and scripts, executables and web pages (`.php`, `.html`, `.js`, `.svgz`, `.xml` …) are refused whatever the list says;
- the content is checked too: a web page or script named `notes.txt` is refused;
- SVGs must be free of scripts, event handlers, `javascript:` links, embedded documents and entities. They're refused rather than cleaned, so nothing is changed silently; logos and icons from design tools pass;
- files over the size limit are refused with a clear message.

Stored names are new and random, never the uploader's.
