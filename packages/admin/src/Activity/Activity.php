<?php

declare(strict_types=1);

namespace Sunrice\Admin\Activity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

    /** Security actions that read better as a phrase after the person's name. */
    private const PHRASES = [
        'page refused' => 'was refused a page',
        'login refused' => 'was refused login',
        'login blocked' => 'was blocked from logging in',
        'upload blocked' => 'had an upload blocked',
    ];

    /** Whether it is about the account of whoever did it ("Rina logged in"). */
    public function isAboutItsActor(): bool
    {
        return $this->subject_type === 'account' && $this->subject_id !== null && $this->subject_id === $this->user_id;
    }

    /**
     * The line as a sentence: "Rina updated Kettle", "Rina logged in",
     * "Login failed for Dewi", "Roles changed for Dewi by Rina".
     */
    public function sentence(): string
    {
        $who = $this->user_name ?? ($this->source === 'system' ? 'System' : null);
        if ($this->isAboutItsActor() || $this->subject_label === null) {
            $phrase = self::PHRASES[$this->action] ?? $this->action;

            return $who === null ? Str::ucfirst($phrase) : "{$who} {$phrase}";
        }
        if ($this->subject_type === 'account' || $who === null) {
            return Str::ucfirst($this->action)." for {$this->subject_label}".($who === null ? '' : " by {$who}");
        }

        return "{$who} {$this->action} {$this->subject_label}";
    }

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
