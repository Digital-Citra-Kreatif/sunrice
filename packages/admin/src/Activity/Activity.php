<?php

declare(strict_types=1);

namespace Sunrice\Admin\Activity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One line of the activity log, written by ActivityLog.
 *
 * @property int $id
 * @property string $source admin, ai, system or security
 * @property string|null $via the AI access token's name, for "ai"
 * @property string|null $user_id
 * @property string|null $user_name
 * @property string $action
 * @property string $subject_type
 * @property string|null $subject_id
 * @property string|null $subject_label
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 *
 * @api
 */
class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'sunrice_activity';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    /** Delete lines older than this many days, in chunks; how many. */
    public static function prune(int $days): int
    {
        $deleted = 0;
        $before = now()->subDays(max(0, $days));
        do {
            $ids = static::query()->where('created_at', '<', $before)->orderBy('id')->limit(1000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : static::query()->whereKey($ids)->toBase()->delete();
        } while ($ids->count() === 1000);

        return $deleted;
    }
}
