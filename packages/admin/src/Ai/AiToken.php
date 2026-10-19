<?php

declare(strict_types=1);

namespace Sunrice\Admin\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An AI access token. The plain token is shown once when it's created;
 * only its SHA-256 hash is stored.
 *
 * @property int $id
 * @property string $user_id
 * @property string $name
 * @property string $token_hash
 * @property bool $read_only
 * @property Carbon|null $expires_at
 * @property Carbon|null $last_used_at
 * @property string|null $last_used_ip
 * @property Carbon|null $created_at
 *
 * @api
 */
class AiToken extends Model
{
    public const PREFIX = 'sra_';

    protected $table = 'sunrice_ai_tokens';

    protected $guarded = [];

    protected $hidden = ['token_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['read_only' => 'boolean', 'expires_at' => 'datetime', 'last_used_at' => 'datetime'];
    }

    /**
     * A new token for a user: the record and the plain token (show it once).
     *
     * @return array{0: AiToken, 1: string}
     */
    public static function issue(string $userId, string $name, bool $readOnly = false, ?Carbon $expiresAt = null): array
    {
        $plain = self::PREFIX.Str::random(40);
        $token = static::query()->create([
            'user_id' => $userId,
            'name' => Str::limit(trim($name), 100, ''),
            'token_hash' => hash('sha256', $plain),
            'read_only' => $readOnly,
            'expires_at' => $expiresAt,
        ]);

        return [$token, $plain];
    }

    /** The token for this plain value, if it exists and hasn't expired. */
    public static function findValid(string $plain): ?self
    {
        if (! str_starts_with($plain, self::PREFIX) || strlen($plain) > 200) {
            return null;
        }
        $token = static::query()->where('token_hash', hash('sha256', $plain))->first();

        return $token !== null && ($token->expires_at === null || $token->expires_at->isFuture()) ? $token : null;
    }
}
