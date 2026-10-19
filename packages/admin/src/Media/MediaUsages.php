<?php

declare(strict_types=1);

namespace Sunrice\Admin\Media;

use Illuminate\Support\Facades\DB;

/**
 * Where media files are used. Plugins report what their items use when
 * they're saved, and forget them when they're deleted; the media library
 * shows "Used by" and asks before deleting a file in use.
 *
 *     app(MediaUsages::class)->sync('shop.product', (string) $product->id, [3, 7], $product->name, route('…'));
 *     app(MediaUsages::class)->forget('shop.product', (string) $product->id);
 *
 * @api
 */
final class MediaUsages
{
    /**
     * Records the media an item uses now (replacing what it used before).
     *
     * @param  string  $type  the kind of item, starting with your plugin's id: "shop.product"
     * @param  iterable<int>  $mediaIds
     * @param  string  $label  what people see: "Product: Kettle"
     * @param  string|null  $url  where to open it in the panel
     * @param  string  $locale  the item's language, when each is tracked apart
     */
    public function sync(string $type, string $itemId, iterable $mediaIds, string $label, ?string $url = null, string $locale = ''): void
    {
        $ids = [];
        foreach ($mediaIds as $id) {
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        // Only files that exist (a deleted one may linger in stored data).
        $existing = [];
        foreach ($ids === [] ? [] : Media::query()->withTrashed()->whereKey(array_values($ids))->pluck('id') as $id) {
            $existing[] = is_numeric($id) ? (int) $id : 0;
        }
        $ids = $existing;

        DB::transaction(function () use ($type, $itemId, $ids, $label, $url, $locale): void {
            MediaUsage::query()->where('type', $type)->where('item_id', $itemId)->where('locale', $locale)->delete();
            $now = now();
            $rows = array_map(fn (int $id): array => [
                'media_id' => $id, 'type' => $type, 'item_id' => $itemId, 'locale' => $locale,
                'label' => mb_substr($label, 0, 255), 'url' => $url, 'created_at' => $now, 'updated_at' => $now,
            ], $ids);
            if ($rows !== []) {
                MediaUsage::query()->insert($rows);
            }
        });
    }

    /** Forgets what an item uses: in one language, or in all (null). */
    public function forget(string $type, string $itemId, ?string $locale = null): void
    {
        MediaUsage::query()->where('type', $type)->where('item_id', $itemId)
            ->when($locale !== null, fn ($query) => $query->where('locale', $locale))->delete();
    }

    /**
     * Where a file is used, an item once even when it uses the file twice.
     *
     * @return list<array{type: string, label: string, url: string|null}>
     */
    public function of(Media $media): array
    {
        return array_values(MediaUsage::query()->where('media_id', $media->id)->orderBy('label')->get()
            ->unique(fn (MediaUsage $u): string => $u->type.':'.$u->item_id.':'.$u->locale)
            ->map(fn (MediaUsage $u): array => ['type' => $u->type, 'label' => $u->label, 'url' => $u->url])
            ->all());
    }
}
