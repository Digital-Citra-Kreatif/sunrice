<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Sunrice\Admin\Media\SafeSvg;

/**
 * SVGs uploaded before their size was read from the viewBox have no
 * width or height, so a wide logo was taken for a square icon (in the
 * sidebar and as the favicon). Reads each one's size from its file once.
 * A file that's gone or unreadable is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Written out, without bindings: the index-name test runs migrations on a pretend connection.
        DB::table('sunrice_media')->whereRaw("mime_type in ('image/svg+xml', 'image/svg')")
            ->where(fn ($query) => $query->whereNull('width')->orWhereNull('height'))
            ->orderBy('id')->select(['id', 'disk', 'path'])
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    try {
                        $svg = Storage::disk((string) $row->disk)->get((string) $row->path);
                    } catch (Throwable) {
                        continue;
                    }
                    [$width, $height] = is_string($svg) ? SafeSvg::dimensions($svg) : [null, null];
                    if ($width !== null && $height !== null) {
                        DB::table('sunrice_media')->where('id', $row->id)->update(['width' => $width, 'height' => $height]);
                    }
                }
            });
    }

    public function down(): void
    {
        // The sizes are right: nothing to undo.
    }
};
