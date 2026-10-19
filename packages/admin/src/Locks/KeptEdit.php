<?php

declare(strict_types=1);

namespace Sunrice\Admin\Locks;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Support\Carbon;

/**
 * Unsaved changes of an editor whose lock was taken over, kept so they
 * can be loaded again. Pruned after a month.
 *
 * @property int $id
 * @property string $type
 * @property string $item_id
 * @property string $locale
 * @property string|null $user_id
 * @property string|null $user_name
 * @property string|null $taken_by
 * @property array<string, mixed> $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @api
 */
class KeptEdit extends Model
{
    use Prunable;

    /** Days they're kept. */
    public const KEEP_DAYS = 30;

    protected $table = 'sunrice_kept_edits';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }
}
