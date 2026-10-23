<?php

declare(strict_types=1);

namespace Sunrice\Admin\Activity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Sunrice\Admin\Support\Say;

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

    /** The password rules a weak password failed ("weak password at sign-in"), as its note names them. */
    private const PASSWORD_RULES = [
        'length' => 'length',
        'mixed' => 'upper and lower case',
        'numbers' => 'a number',
        'symbols' => 'a symbol',
        'personal' => 'email address or name',
        'leaked' => 'data leak',
    ];

    /** Whether it is about the account of whoever did it ("Rina logged in"). */
    public function isAboutItsActor(): bool
    {
        return $this->subject_type === 'account' && $this->subject_id !== null && $this->subject_id === $this->user_id;
    }

    /**
     * An action as the verb of a sentence in the panel's language
     * ("published" → "menerbitkan"): the translation of "activity: <action>"
     * when there is one, for a word that means something else on its own
     * (the CMS's "published" is also a status), else of the action itself.
     */
    public static function verb(string $action): string
    {
        $key = "activity: {$action}";
        $said = __($key);

        return is_string($said) && $said !== $key ? $said : Say::it($action);
    }

    /**
     * The line as a sentence: "Rina updated Kettle", "Rina logged in",
     * "Login failed for Dewi", "Roles changed for Dewi by Rina".
     */
    public function sentence(): string
    {
        $who = $this->user_name ?? ($this->source === 'system' ? Say::it('System') : null);
        $action = self::verb($this->action);
        if ($this->isAboutItsActor() || $this->subject_label === null) {
            $phrase = isset(self::PHRASES[$this->action]) ? Say::it(self::PHRASES[$this->action]) : $action;

            return $who === null ? Str::ucfirst($phrase) : "{$who} {$phrase}";
        }
        if ($this->subject_type === 'account' || $who === null) {
            $replace = ['action' => Str::ucfirst($action), 'subject' => $this->subject_label, 'who' => (string) $who];

            $said = $who === null ? __(':action for :subject', $replace) : __(':action for :subject by :who', $replace);

            return is_string($said) ? $said : "{$replace['action']} {$this->subject_label}";
        }

        return "{$who} {$action} {$this->subject_label}";
    }

    /**
     * The line's note in the panel's language: the password rules a weak
     * password failed ("fails: length, a symbol"), or the note it was
     * recorded with, said through __() when there is a line for it.
     */
    public function note(): ?string
    {
        $properties = $this->properties ?? [];
        $problems = $properties['problems'] ?? null;
        if (is_array($problems) && $problems !== [] && array_is_list($problems)) {
            $rules = array_map(fn (mixed $rule): string => is_string($rule) ? (string) Say::it(self::PASSWORD_RULES[$rule] ?? $rule) : '', $problems);
            $said = __('fails: :rules', ['rules' => implode(', ', array_filter($rules, fn (string $rule): bool => $rule !== ''))]);

            return is_string($said) ? $said : null;
        }
        $note = $properties['note'] ?? null;

        return is_string($note) && $note !== '' ? (string) Say::it($note) : null;
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
